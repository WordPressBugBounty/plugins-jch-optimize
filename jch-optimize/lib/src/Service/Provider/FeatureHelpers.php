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

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Joomla\DI\ServiceProviderInterface;
use _JchOptimizeVendor\V91\Laminas\EventManager\LazyListener;
use _JchOptimizeVendor\V91\Laminas\EventManager\SharedEventManager;
use _JchOptimizeVendor\V91\Psr\Log\LoggerInterface;
use JchOptimize\Core\Admin\AdminHelper;
use JchOptimize\Core\Cdn\Cdn;
use JchOptimize\Core\FeatureHelpers\CdnDomains;
use JchOptimize\Core\FeatureHelpers\DynamicJs;
use JchOptimize\Core\FeatureHelpers\Fonts;
use JchOptimize\Core\FeatureHelpers\Http2Excludes;
use JchOptimize\Core\FeatureHelpers\LazyLoadExtended;
use JchOptimize\Core\FeatureHelpers\LCPImages;
use JchOptimize\Core\FeatureHelpers\ReduceDom;
use JchOptimize\Core\FeatureHelpers\ResponsiveImages;
use JchOptimize\Core\FeatureHelpers\Webp;
use JchOptimize\Core\FeatureHelpers\YouTubeFacade;
use JchOptimize\Core\Html\AsyncManager;
use JchOptimize\Core\Html\CacheManager;
use JchOptimize\Core\Html\Callbacks\ReduceDom as ReduceDomCallback;
use JchOptimize\Core\Html\FilesManager;
use JchOptimize\Core\Html\HtmlManager;
use JchOptimize\Core\Html\HtmlProcessor;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Platform\ProfilerInterface;
use JchOptimize\Core\Platform\UtilityInterface;
use JchOptimize\Core\Preloads\Http2Preload;
use JchOptimize\Core\Preloads\Preconnector;
use JchOptimize\Core\Registry;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

class FeatureHelpers implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->share(CdnDomains::class, function (Container $container): CdnDomains {
            return new CdnDomains(
                $container,
                $container->get(Registry::class),
                $container->get(Cdn::class)
            );
        });

        $container->share(DynamicJs::class, function (Container $container): DynamicJs {
            return new DynamicJs(
                $container,
                $container->get(Registry::class),
                $container->get(CacheManager::class),
                $container->get(HtmlManager::class),
            );
        });

        $container->share(Fonts::class, function (Container $container): Fonts {
            return new Fonts(
                $container,
                $container->get(Registry::class),
                $container->get(HtmlManager::class),
                $container->get(Preconnector::class)
            );
        });

        $container->share(Http2Excludes::class, function (Container $container): Http2Excludes {
            return new Http2Excludes(
                $container,
                $container->get(Registry::class),
                $container->get(Http2Preload::class)
            );
        });

        $container->share(LazyLoadExtended::class, function (Container $container): LazyLoadExtended {
            return new LazyLoadExtended(
                $container,
                $container->get(Registry::class),
                $container->get(CacheManager::class),
                $container->get(PathsInterface::class)
            );
        });

        $container->share(ReduceDom::class, function (Container $container): ReduceDom {
            $reduceDom = new ReduceDom(
                $container,
                $container->get(Registry::class),
                $container->get(HtmlProcessor::class),
                $container->get(ReduceDomCallback::class),
                $container->get(CacheManager::class),
                $container->get(ProfilerInterface::class),
                $container->get(PathsInterface::class)
            );
            $reduceDom->setLogger($container->get(LoggerInterface::class));

            return $reduceDom;
        });

        $container->share(ReduceDomCallback::class, function (Container $container): ReduceDomCallback {
            return new ReduceDomCallback(
                $container,
                $container->get(Registry::class),
            );
        });

        $container->share(Webp::class, function (Container $container): Webp {
            return new Webp(
                $container,
                $container->get(Registry::class),
                $container->get(Cdn::class),
                $container->get(PathsInterface::class),
                $container->get(AdminHelper::class),
                $container->get(UtilityInterface::class)
            );
        });

        $container->share(ResponsiveImages::class, function (Container $container): ResponsiveImages {
            return new ResponsiveImages(
                $container,
                $container->get(Registry::class),
                $container->get(Cdn::class),
                $container->get(PathsInterface::class)
            );
        });

        $container->share(LCPImages::class, function (Container $container): LCPImages {
            return new LCPImages(
                $container,
                $container->get(Registry::class),
                $container->get(Http2Preload::class),
                $container->get(ResponsiveImages::class)
            );
        });

        $container->share(AsyncManager::class, function (Container $container): AsyncManager {
            return new AsyncManager(
                $container->get(Registry::class),
                $container->get(CacheManager::class),
                $container->get(PathsInterface::class)
            );
        });

        $container->share(YouTubeFacade::class, function (Container $container): YouTubeFacade {
            return new YouTubeFacade(
                $container,
                $container->get(Registry::class),
                $container->get(CacheManager::class),
                $container->get(PathsInterface::class),
            );
        });

        //Set up events management
        /** @var SharedEventManager $sharedEvents */
        $sharedEvents = $container->get(SharedEventManager::class);

        $sharedEvents->attach(
            HtmlManager::class,
            'postProcessHtml',
            new LazyListener([
                /** @see Fonts::appendOptimizedFontsToHead() */
                'listener' => Fonts::class,
                'method' => 'appendOptimizedFontsToHead'
            ], $container)
        );

        $sharedEvents->attach(
            HtmlManager::class,
            'postProcessHtml',
            new LazyListener([
                /** @see LazyLoadExtended::lazyLoadCssBackgroundImages() */
                'listener' => LazyLoadExtended::class,
                'method' => 'lazyLoadCssBackgroundImages'
            ], $container)
        );

        $sharedEvents->attach(
            HtmlManager::class,
            'postProcessHtml',
            new LazyListener([
                /** @see LazyLoadExtended::loadLazyLoadAssets() */
                'listener' => LazyLoadExtended::class,
                'method' => 'loadLazyLoadAssets'
            ], $container)
        );

        $sharedEvents->attach(
            HtmlManager::class,
            'postProcessHtml',
            new LazyListener([
                /** @see YouTubeFacade::loadYouTubeFacadeAssets() */
                'listener' => YouTubeFacade::class,
                'method' => 'loadYouTubeFacadeAssets'
            ], $container)
        );

        $sharedEvents->attach(
            HtmlManager::class,
            'postProcessHtml',
            new LazyListener([
                /** @see ReduceDom::process() */
                'listener' => ReduceDom::class,
                'method' => 'process'
            ], $container)
        );

        $sharedEvents->attach(
            HtmlManager::class,
            'postProcessHtml',
            new LazyListener([
                /** @see ReduceDom::loadReduceDomResources() */
                'listener' => ReduceDom::class,
                'method' => 'loadReduceDomResources'
            ], $container)
        );

        $sharedEvents->attach(
            HtmlManager::class,
            'postProcessHtml',
            new LazyListener([
                /** @see AsyncManager::loadAsyncManagerAssets() */
                'listener' => AsyncManager::class,
                 'method' => 'loadAsyncManagerAssets'
            ], $container)
        );

        $sharedEvents->attach(
            HtmlManager::class,
            'postProcessHtml',
            new LazyListener([
                /** @see LCPImages::removeAutoLcp() */
                'listener' => LCPImages::class,
                'method' => 'removeAutoLcp'
            ], $container)
        );
    }
}
