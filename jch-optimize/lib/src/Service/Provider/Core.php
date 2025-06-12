<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Service\Provider;

use _JchOptimizeVendor\V91\Composer\CaBundle\CaBundle;
use _JchOptimizeVendor\V91\GuzzleHttp\Client;
use _JchOptimizeVendor\V91\GuzzleHttp\RequestOptions;
use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Joomla\DI\ServiceProviderInterface;
use _JchOptimizeVendor\V91\Joomla\Input\Input;
use _JchOptimizeVendor\V91\Laminas\Cache\Pattern\CallbackCache;
use _JchOptimizeVendor\V91\Laminas\Cache\Pattern\CaptureCache;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\StorageInterface;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\TaggableInterface;
use _JchOptimizeVendor\V91\Laminas\EventManager\LazyListener;
use _JchOptimizeVendor\V91\Laminas\EventManager\SharedEventManager;
use _JchOptimizeVendor\V91\Laminas\EventManager\SharedEventManagerInterface;
use _JchOptimizeVendor\V91\Psr\Http\Client\ClientInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerInterface;
use Exception;
use JchOptimize\Core\Admin\HtmlCrawler;
use JchOptimize\Core\Admin\Icons;
use JchOptimize\Core\Admin\MultiSelectItems;
use JchOptimize\Core\Cdn\Cdn;
use JchOptimize\Core\Combiner;
use JchOptimize\Core\ConfigureHelper;
use JchOptimize\Core\Css\Callbacks\CorrectUrls;
use JchOptimize\Core\Css\Callbacks\ExtractCriticalCss;
use JchOptimize\Core\Css\Callbacks\FormatCss;
use JchOptimize\Core\Css\Callbacks\HandleAtRules;
use JchOptimize\Core\Css\Callbacks\PostProcessCriticalCss;
use JchOptimize\Core\Css\CssProcessor;
use JchOptimize\Core\Css\Sprite\Controller;
use JchOptimize\Core\Css\Sprite\Generator;
use JchOptimize\Core\FeatureHelpers\DynamicSelectors;
use JchOptimize\Core\FileUtils;
use JchOptimize\Core\Html\CacheManager;
use JchOptimize\Core\Html\FilesManager;
use JchOptimize\Core\Html\HtmlManager;
use JchOptimize\Core\Html\HtmlProcessor;
use JchOptimize\Core\ImageAttributes;
use JchOptimize\Core\Model\CacheMaintainer;
use JchOptimize\Core\Optimize;
use JchOptimize\Core\PageCache\CaptureCache as CoreCaptureCache;
use JchOptimize\Core\PageCache\PageCache;
use JchOptimize\Core\Platform\CacheInterface;
use JchOptimize\Core\Platform\ExcludesInterface;
use JchOptimize\Core\Platform\HooksInterface;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Platform\ProfilerInterface;
use JchOptimize\Core\Platform\UtilityInterface;
use JchOptimize\Core\Preloads\Http2Preload;
use JchOptimize\Core\Preloads\Preconnector;
use JchOptimize\Core\Registry;
use JchOptimize\Core\SystemUri;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

class Core implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        //Html
        $container->share(CacheManager::class, [$this, 'getCacheManagerService']);
        $container->share(FilesManager::class, [$this, 'getFilesManagerService']);
        $container->share(HtmlManager::class, [$this, 'getHtmlManagerService']);
        $container->share(HtmlProcessor::class, [$this, 'getHtmlProcessorService']);
        //Css
        $container->set(CssProcessor::class, [$this, 'getCssProcessorService']);
        //Core
        $container->share(Cdn::class, [$this, 'getCdnService']);
        $container->share(Combiner::class, [$this, 'getCombinerService']);
        $container->share(FileUtils::class, [$this, 'getFileUtilsService']);
        $container->share(Http2Preload::class, [$this, 'getHttp2PreloadService']);
        $container->share(Optimize::class, [$this, 'getOptimizeService']);
        $container->share(Preconnector::class, [$this, 'getPreconnectorService']);
        $container->share(ImageAttributes::class, [$this, 'getImageAttributesService']);
        //PageCache
        $container->share(PageCache::class, [$this, 'getPageCacheService']);
        $container->share(CoreCaptureCache::class, [$this, 'getCaptureCacheService']);
        //Admin
        $container->share(HtmlCrawler::class, [$this, 'getHtmlCrawlerService']);
        $container->share(Icons::class, [$this, 'getIconsService']);
        $container->share(MultiSelectItems::class, [$this, 'getMultiSelectItemsService']);
        //Sprite
        $container->set(Generator::class, [$this, 'getSpriteGeneratorService']);
        $container->set(Controller::class, [$this, 'getSpriteControllerService']);
        //Vendor
        $container->share(ClientInterface::class, [$this, 'getClientInterfaceService']);
        //MVC
        $container->alias(Input::class, 'input')
            ->share('input', [$this, 'getInputService']);
        //Development
        $container->share(ConfigureHelper::class, [$this, 'getConfigureHelperService']);
        //Utility
        $container->share(CacheMaintainer::class, [$this, 'getCacheMaintainerService']);
        //Feature Helpers
        $container->share(DynamicSelectors::class, function (Container $container): DynamicSelectors {
            return new DynamicSelectors(
                $container,
                $container->get(Registry::class)
            );
        });

        //Set up events management
        /** @var SharedEventManager $sharedEvents */
        $sharedEvents = $container->get(SharedEventManager::class);

        $sharedEvents->attach(
            HtmlManager::class,
            'preProcessHtml',
            new LazyListener([
                /** @see HtmlManager::addCustomCss() */
                'listener' => HtmlManager::class,
                'method' => 'addCustomCss'
            ], $container)
        );

        $sharedEvents->attach(
            HtmlManager::class,
            'preProcessHtml',
            new LazyListener([
                /** @see ImageAttributes::loadImageAttributesCss() */
                'listener' => ImageAttributes::class,
                'method' => 'loadImageAttributesCss'
            ], $container)
        );

        $sharedEvents->attach(
            HtmlManager::class,
            'postProcessHtml',
            new LazyListener([
                /** @see Http2Preload::preloadAssets() */
                'listener' => Http2Preload::class,
                'method' => 'preloadAssets'
            ], $container),
            200
        );

        $sharedEvents->attach(
            HtmlManager::class,
            'postProcessHtml',
            new LazyListener([
                /** @see Http2Preload::sendLinkHeaders() */
                'listener' => Http2Preload::class,
                'method' => 'sendLinkHeaders'
            ], $container),
        );

        $sharedEvents->attach(
            HtmlManager::class,
            'postProcessHtml',
            new LazyListener([
                /** @see ConfigureHelper::loadNumElementsScript() */
                'listener' => ConfigureHelper::class,
                'method' => 'loadNumElementsScript'
            ], $container),
            200
        );

        $sharedEvents->attach(
            HtmlManager::class,
            'postProcessHtml',
            new LazyListener([
                /** @see Preconnector::addPreConnectsToHead() */
                'listener' => Preconnector::class,
                'method' => 'addPreconnectsToHead'
            ], $container),
            300
        );

        $sharedEvents->attach(
            HtmlManager::class,
            'postProcessHtml',
            new LazyListener([
                /** @see ConfigureHelper::loadDynamicCssElementsScript() */
                'listener' => ConfigureHelper::class,
                'method' => 'loadDynamicCssElementsScript'
            ], $container)
        );

        if (JCH_PRO) {
            $sharedEvents->attach(
                HtmlManager::class,
                'postProcessHtml',
                new LazyListener([
                    /** @see Http2Preload::addModulePreloadsToHtml() */
                    'listener' => Http2Preload::class,
                    'method' => 'addModulePreloadsToHtml'
                ], $container),
                100
            );
        }
    }

    /**
     * @psalm-suppress InvalidArgument
     */
    public function getCacheManagerService(Container $container): CacheManager
    {
        $cacheManager = new CacheManager(
            $container->get(Registry::class),
            $container->get(HtmlManager::class),
            $container->get(Combiner::class),
            $container->get(FilesManager::class),
            $container->get(CallbackCache::class),
            $container->get(TaggableInterface::class),
            $container->get(Http2Preload::class),
            $container->get(HtmlProcessor::class),
            $container->get(ImageAttributes::class),
            $container->get(ProfilerInterface::class),
            $container->get(PathsInterface::class),
        );
        $cacheManager->setContainer($container);
        $cacheManager->setLogger($container->get(LoggerInterface::class));

        return $cacheManager;
    }

    public function getFilesManagerService(Container $container): FilesManager
    {
        return (new FilesManager(
            $container->get(Registry::class),
            $container->get(FileUtils::class),
            $container->get(ExcludesInterface::class),
            $container->get(ClientInterface::class)
        ))->setContainer($container);
    }

    public function getHtmlManagerService(Container $container): HtmlManager
    {
        return (new HtmlManager(
            $container->get(Registry::class),
            $container->get(HtmlProcessor::class),
            $container->get(FilesManager::class),
            $container->get(Cdn::class),
            $container->get(Http2Preload::class),
            $container->get(StorageInterface::class),
            $container->get(SharedEventManagerInterface::class),
            $container->get(ProfilerInterface::class),
            $container->get(PathsInterface::class)
        ))->setContainer($container);
    }

    public function getHtmlProcessorService(Container $container): HtmlProcessor
    {
        $htmlProcessor = new HtmlProcessor(
            $container->get(Registry::class),
            $container->get(ProfilerInterface::class)
        );
        $htmlProcessor->setContainer($container)
            ->setLogger($container->get(LoggerInterface::class));

        return $htmlProcessor;
    }

    public function getCssProcessorService(Container $container): CssProcessor
    {
        $cssProcessor = new CssProcessor(
            $container->get(Registry::class),
            $container->get(CorrectUrls::class),
            $container->get(ExtractCriticalCss::class),
            $container->get(FormatCss::class),
            $container->get(HandleAtRules::class),
            $container->get(PostProcessCriticalCss::class),
            $container->get(ProfilerInterface::class)
        );
        $cssProcessor->setContainer($container)
            ->setLogger($container->get(LoggerInterface::class));

        return $cssProcessor;
    }

    public function getCdnService(Container $container): Cdn
    {
        return (new Cdn(
            $container->get(Registry::class)
        ))->setContainer($container);
    }

    /**
     * @psalm-suppress InvalidArgument
     */
    public function getCombinerService(Container $container): Combiner
    {
        $combiner = new Combiner(
            $container->get(Registry::class),
            $container->get(ClientInterface::class),
            $container->get(ProfilerInterface::class)
        );
        $combiner->setContainer($container)
            ->setLogger($container->get(LoggerInterface::class));

        return $combiner;
    }

    public function getFileUtilsService(): FileUtils
    {
        return new FileUtils();
    }

    public function getHttp2PreloadService(Container $container): Http2Preload
    {
        return (new Http2Preload(
            $container->get(Registry::class),
            $container->get(Cdn::class),
            $container->get(CacheInterface::class),
            $container->get(HooksInterface::class),
            $container->get(UtilityInterface::class)
        ))->setContainer($container);
    }

    public function getOptimizeService(Container $container): Optimize
    {
        $optimize = new Optimize(
            $container->get(Registry::class),
            $container->get(HtmlProcessor::class),
            $container->get(CacheManager::class),
            $container->get(HtmlManager::class),
            $container->get(Http2Preload::class),
            $container->get(ProfilerInterface::class),
            $container->get(UtilityInterface::class)
        );
        $optimize->setContainer($container)
            ->setLogger($container->get(LoggerInterface::class));

        return $optimize;
    }

    public function getPreconnectorService(Container $container): Preconnector
    {
        $preconnector = new Preconnector(
            $container->get(Registry::class),
            $container->get(HtmlProcessor::class)
        );
        $preconnector->setLogger($container->get(LoggerInterface::class));

        return $preconnector;
    }

    public function getImageAttributesService(Container $container): ImageAttributes
    {
        return new ImageAttributes(
            $container->get(Registry::class),
            $container->get(Cdn::class),
            $container->get(PathsInterface::class)
        );
    }

    /**
     * @psalm-suppress InvalidArgument
     */
    public function getPageCacheService(Container $container): PageCache
    {
        $params = $container->get(Registry::class);
        $cacheUtils = $container->get(CacheInterface::class);

        if (JCH_PRO && $params->get('pro_capture_cache_enable', '0') && !$cacheUtils->isCaptureCacheIncompatible()) {
            return $container->get(CoreCaptureCache::class);
        }

        $pageCache = (new PageCache(
            $container->get(Registry::class),
            $container->get(Input::class),
            $container->get('page_cache'),
            $container->get(TaggableInterface::class),
            $container->get(CacheInterface::class),
            $container->get(HooksInterface::class),
            $container->get(UtilityInterface::class)
        ))->setContainer($container);
        $pageCache->setLogger($container->get(LoggerInterface::class));

        return $pageCache;
    }

    /**
     * @psalm-suppress InvalidArgument
     */
    public function getCaptureCacheService(Container $container): CoreCaptureCache
    {
        $captureCache = (new CoreCaptureCache(
            $container->get(Registry::class),
            $container->get(Input::class),
            $container->get('page_cache'),
            $container->get(TaggableInterface::class),
            $container->get(CaptureCache::class),
            $container->get(CacheInterface::class),
            $container->get(HooksInterface::class),
            $container->get(UtilityInterface::class),
            $container->get(PathsInterface::class),
        ))->setContainer($container);
        $captureCache->setLogger($container->get(LoggerInterface::class));

        return $captureCache;
    }

    public function getIconsService(Container $container): Icons
    {
        return (new Icons(
            $container->get(Registry::class),
            $container->get(CacheInterface::class),
            $container->get(PathsInterface::class),
            $container->get(UtilityInterface::class)
        ))->setContainer($container);
    }

    public function getMultiSelectItemsService(Container $container): MultiSelectItems
    {
        return (new MultiSelectItems(
            $container->get(Registry::class),
            $container->get(CallbackCache::class),
            $container->get(ExcludesInterface::class),
            $container->get(ProfilerInterface::class)
        ))->setContainer($container);
    }

    public function getSpriteGeneratorService(Container $container): Generator
    {
        $spriteGenerator = new Generator(
            $container->get(Registry::class),
            $container->get(PathsInterface::class),
            $container->get(ProfilerInterface::class),
            $container->get(Controller::class)
        );
        $spriteGenerator->setContainer($container)
            ->setLogger($container->get(LoggerInterface::class));

        return $spriteGenerator;
    }

    /**
     * @throws Exception
     */
    public function getSpriteControllerService(Container $container): ?Controller
    {
        try {
            return (new Controller(
                $container->get(Registry::class),
                $container->get(LoggerInterface::class),
                $container->get(PathsInterface::class),
                $container->get(ProfilerInterface::class)
            ))->setContainer($container);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * @return Client&ClientInterface
     */
    public function getClientInterfaceService()
    {
        return new Client([
            'base_uri' => SystemUri::currentUri(),
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::VERIFY => CaBundle::getBundledCaBundlePath(),
            RequestOptions::TIMEOUT => 5,
            RequestOptions::CONNECT_TIMEOUT => 5,
            RequestOptions::READ_TIMEOUT => 5,
            RequestOptions::HEADERS => [
                'User-Agent' => $_SERVER['HTTP_USER_AGENT'] ?? '*'
            ]
        ]);
    }

    public function getInputService(): Input
    {
        return new Input($_REQUEST);
    }

    public function getConfigureHelperService(Container $container): ConfigureHelper
    {
        return new ConfigureHelper(
            $container->get(Registry::class),
            $container->get(HtmlManager::class),
            $container->get(PathsInterface::class)
        );
    }

    public function getCacheMaintainerService(Container $container): CacheMaintainer
    {
        return new CacheMaintainer(
            $container->get(StorageInterface::class),
            $container->get(TaggableInterface::class),
            $container->get(PageCache::class),
            $container->get(PathsInterface::class),
            $container->get(CacheInterface::class)
        );
    }

    public function getHtmlCrawlerService(Container $container): HtmlCrawler
    {
        $htmlCrawler = new HtmlCrawler(
            $container->get(Registry::class),
            $container,
            $container->get(ClientInterface::class),
            $container->get(PathsInterface::class)
        );
        $htmlCrawler->setLogger($container->get(LoggerInterface::class));

        return $htmlCrawler;
    }
}
