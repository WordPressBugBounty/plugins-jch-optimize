<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2025 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\WordPress\Service;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Joomla\DI\ServiceProviderInterface;
use _JchOptimizeVendor\V91\Psr\Http\Client\ClientInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerInterface;
use JchOptimize\Core\Platform\CacheInterface;
use JchOptimize\Core\Platform\ExcludesInterface;
use JchOptimize\Core\Platform\HooksInterface;
use JchOptimize\Core\Platform\HtmlInterface;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Platform\PluginInterface;
use JchOptimize\Core\Platform\ProfilerInterface;
use JchOptimize\Core\Platform\UtilityInterface;
use JchOptimize\WordPress\Platform\Cache;
use JchOptimize\WordPress\Platform\Excludes;
use JchOptimize\WordPress\Platform\Hooks;
use JchOptimize\WordPress\Platform\Html;
use JchOptimize\WordPress\Platform\Paths;
use JchOptimize\WordPress\Platform\Plugin;
use JchOptimize\WordPress\Platform\Profiler;
use JchOptimize\WordPress\Platform\Utility;

class PlatformProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->share(CacheInterface::class, function (): CacheInterface {
            return new Cache();
        });

        $container->share(ExcludesInterface::class, function (Container $container): ExcludesInterface {
            return new Excludes(
                $container->get(PathsInterface::class)
            );
        });

        $container->share(HooksInterface::class, function (): HooksInterface {
            return new Hooks();
        });

        $container->share(PathsInterface::class, function (): PathsInterface {
            return new Paths();
        });

        $container->share(PluginInterface::class, function (): PluginInterface {
            return new Plugin();
        });

        $container->share(UtilityInterface::class, function (): UtilityInterface {
            return new Utility();
        });

        $container->share(HtmlInterface::class, function (Container $container): HtmlInterface {
            $html = new Html(
                $container->get(ClientInterface::class),
                $container->get(ProfilerInterface::class),
                $container->get(UtilityInterface::class)
            );
            $html->setLogger($container->get(LoggerInterface::class));

            return $html;
        });

        $container->share(ProfilerInterface::class, function (Container $container): ProfilerInterface {
            return new Profiler();
        });
    }
}
