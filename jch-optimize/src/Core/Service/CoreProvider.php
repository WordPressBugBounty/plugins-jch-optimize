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

namespace JchOptimize\Core\Service;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use JchOptimize\Core\Admin\AbstractHtml;
use JchOptimize\Core\Admin\Icons;
use JchOptimize\Core\Admin\ImageUploader;
use JchOptimize\Core\Admin\MultiSelectItems;
use JchOptimize\Core\Cdn;
use JchOptimize\Core\Combiner;
use JchOptimize\Core\Css\Callbacks\CombineMediaQueries;
use JchOptimize\Core\Css\Callbacks\CorrectUrls;
use JchOptimize\Core\Css\Callbacks\ExtractCriticalCss;
use JchOptimize\Core\Css\Callbacks\FormatCss;
use JchOptimize\Core\Css\Callbacks\HandleAtRules;
use JchOptimize\Core\Css\Processor as CssProcessor;
use JchOptimize\Core\Css\Sprite\Controller;
use JchOptimize\Core\Css\Sprite\Generator;
use JchOptimize\Core\Exception;
use JchOptimize\Core\FileUtils;
use JchOptimize\Core\Html\CacheManager;
use JchOptimize\Core\Html\FilesManager;
use JchOptimize\Core\Html\LinkBuilder;
use JchOptimize\Core\Html\Processor as HtmlProcessor;
use JchOptimize\Core\Http2Preload;
use JchOptimize\Core\Optimize;
use JchOptimize\Core\PageCache\CaptureCache as CoreCaptureCache;
use JchOptimize\Core\PageCache\PageCache;
use JchOptimize\Core\SystemUri;
use JchOptimize\Platform\Cache;
use JchOptimize\Platform\Html;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use Laminas\Cache\Pattern\CallbackCache;
use Laminas\Cache\Pattern\CaptureCache;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Cache\Storage\TaggableInterface;
use Laminas\EventManager\LazyListener;
use Laminas\EventManager\SharedEventManager;
use Laminas\EventManager\SharedEventManagerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

class CoreProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        //Html
        $container->share(CacheManager::class, [$this, 'getCacheManagerService'], true);
        $container->share(FilesManager::class, [$this, 'getFilesManagerService'], true);
        $container->share(LinkBuilder::class, [$this, 'getLinkBuilderService'], true);
        $container->share(HtmlProcessor::class, [$this, 'getHtmlProcessorService']);
        //Css
        $container->protect(CssProcessor::class, [$this, 'getCssProcessorService']);
        //Core
        $container->share(Cdn::class, [$this, 'getCdnService'], true);
        $container->share(Combiner::class, [$this, 'getCombinerService'], true);
        $container->share(FileUtils::class, [$this, 'getFileUtilsService'], true);
        $container->share(Http2Preload::class, [$this, 'getHttp2PreloadService'], true);
        $container->share(Optimize::class, [$this, 'getOptimizeService'], true);
        //PageCache
        $container->share(PageCache::class, [$this, 'getPageCacheService'], true);
        $container->share(CoreCaptureCache::class, [$this, 'getCaptureCacheService'], true);
        //Admin
        $container->share(AbstractHtml::class, [$this, 'getAbstractHtmlService'], true);
        $container->share(ImageUploader::class, [$this, 'getImageUploaderService'], true);
        $container->share(Icons::class, [$this, 'getIconsService'], true);
        $container->share(MultiSelectItems::class, [$this, 'getMultiSelectItemsService'], true);
        //Sprite
        $container->protect(Generator::class, [$this, 'getSpriteGeneratorService']);
        $container->set(Controller::class, [$this, 'getSpriteControllerService'], false, false);
        //Vendor
        $container->share(ClientInterface::class, [$this, 'getClientInterfaceService']);

        //Set up events management
        /** @var SharedEventManager $sharedEvents */
        $sharedEvents = $container->get(SharedEventManager::class);

        $sharedEvents->attach(
            LinkBuilder::class,
            'postProcessHtml',
            new LazyListener([
                /** @see Http2Preload::addPreloadsToHtml() */
                'listener' => Http2Preload::class,
                'method' => 'addPreloadsToHtml'
            ], $container),
            200
        );

        if (JCH_PRO) {
            $sharedEvents->attach(
                LinkBuilder::class,
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

    public function getCacheManagerService(Container $container): CacheManager
    {
        $cacheManager = new CacheManager(
            $container->get(Registry::class),
            $container->get(LinkBuilder::class),
            $container->get(Combiner::class),
            $container->get(FilesManager::class),
            $container->get(CallbackCache::class),
            $container->get(TaggableInterface::class),
            $container->get(Http2Preload::class),
            $container->get(HtmlProcessor::class)
        );
        $cacheManager->setContainer($container);
        $cacheManager->setLogger($container->get(LoggerInterface::class));

        return $cacheManager;
    }

    public function getFilesManagerService(Container $container): FilesManager
    {
        return (new FilesManager(
            $container->get(Registry::class),
            $container->get(Http2Preload::class),
            $container->get(FileUtils::class),
            $container->get(ClientInterface::class)
        ))->setContainer($container);
    }

    public function getLinkBuilderService(Container $container): LinkBuilder
    {
        return (new LinkBuilder(
            $container->get(Registry::class),
            $container->get(HtmlProcessor::class),
            $container->get(FilesManager::class),
            $container->get(Cdn::class),
            $container->get(Http2Preload::class),
            $container->get(StorageInterface::class),
            $container->get(SharedEventManagerInterface::class)
        ))->setContainer($container);
    }

    public function getHtmlProcessorService(Container $container): HtmlProcessor
    {
        $htmlProcessor = new HtmlProcessor(
            $container->get(Registry::class),
        );
        $htmlProcessor->setContainer($container)
            ->setLogger($container->get(LoggerInterface::class));

        return $htmlProcessor;
    }

    public function getCssProcessorService(Container $container): CssProcessor
    {
        $cssProcessor = new CssProcessor(
            $container->get(Registry::class),
            $container->get(CombineMediaQueries::class),
            $container->get(CorrectUrls::class),
            $container->get(ExtractCriticalCss::class),
            $container->get(FormatCss::class),
            $container->get(HandleAtRules::class)
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

    public function getCombinerService(Container $container): Combiner
    {
        $combiner = new Combiner(
            $container->get(Registry::class),
            $container->get(CallbackCache::class),
            $container->get(TaggableInterface::class),
            $container->get(FileUtils::class),
            $container->get(ClientInterface::class)
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
            $container->get(Cdn::class)
        ))->setContainer($container);
    }

    public function getOptimizeService(Container $container): Optimize
    {
        $optimize = new Optimize(
            $container->get(Registry::class),
            $container->get(HtmlProcessor::class),
            $container->get(CacheManager::class),
            $container->get(LinkBuilder::class),
            $container->get(Http2Preload::class)
        );
        $optimize->setContainer($container)
            ->setLogger($container->get(LoggerInterface::class));

        return $optimize;
    }

    public function getPageCacheService(Container $container): PageCache
    {
        $params = $container->get(Registry::class);

        if (JCH_PRO && $params->get('pro_capture_cache_enable', '0') && !Cache::isCaptureCacheIncompatible()) {
            return $container->get(CoreCaptureCache::class);
        }

        $pageCache = (new PageCache(
            $container->get(Registry::class),
            $container->get(Input::class),
            $container->get('page_cache'),
            $container->get(TaggableInterface::class),
        ))->setContainer($container);
        $pageCache->setLogger($container->get(LoggerInterface::class));

        return $pageCache;
    }

    public function getCaptureCacheService(Container $container): CoreCaptureCache
    {
        $captureCache = (new CoreCaptureCache(
            $container->get(Registry::class),
            $container->get(Input::class),
            $container->get('page_cache'),
            $container->get(TaggableInterface::class),
            $container->get(CaptureCache::class)
        ))->setContainer($container);
        $captureCache->setLogger($container->get(LoggerInterface::class));

        return $captureCache;
    }

    public function getAbstractHtmlService(Container $container): AbstractHtml
    {
        $html = new Html(
            $container->get(Registry::class),
            $container->get(ClientInterface::class)
        );
        $html->setContainer($container)
            ->setLogger($container->get(LoggerInterface::class));

        return $html;
    }

    /**
     * @throws Exception\InvalidArgumentException
     */
    public function getImageUploaderService(Container $container): ImageUploader
    {
        return new ImageUploader(
            $container->get(Registry::class),
            $container->get(ClientInterface::class)
        );
    }

    public function getIconsService(Container $container): Icons
    {
        return (new Icons(
            $container->get(Registry::class),
        ))->setContainer($container);
    }

    public function getMultiSelectItemsService(Container $container): MultiSelectItems
    {
        return new MultiSelectItems(
            $container->get(Registry::class),
            $container->get(CallbackCache::class),
            $container->get(FileUtils::class)
        );
    }

    public function getSpriteGeneratorService(Container $container): Generator
    {
        $spriteGenerator = new Generator(
            $container->get(Registry::class),
            $container->get(Controller::class)
        );
        $spriteGenerator->setContainer($container)
            ->setLogger($container->get(LoggerInterface::class));

        return $spriteGenerator;
    }

    /**
     * @throws \Exception
     */
    public function getSpriteControllerService(Container $container): ?Controller
    {
        try {
            return (new Controller(
                $container->get(Registry::class),
                $container->get(LoggerInterface::class)
            ))->setContainer($container);
        } catch (\Exception $e) {
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
            RequestOptions::VERIFY => false,
            RequestOptions::HEADERS => [
                'User-Agent' => $_SERVER['HTTP_USER_AGENT'] ?? '*'
            ]
        ]);
    }
}
