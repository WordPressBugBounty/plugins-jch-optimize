<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2021 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\WordPress\Container;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use JchOptimize\Core\AbstractContainerFactory;
use JchOptimize\Core\Service\Provider\PsrLogger;
use JchOptimize\WordPress\Service\ConfigurationProvider;
use JchOptimize\WordPress\Service\CriticalJsConfigureHelperProvider;
use JchOptimize\WordPress\Service\MvcProvider;
use JchOptimize\WordPress\Service\PlatformProvider;
use JchOptimize\WordPress\Service\PluginProvider;

use const JCH_PRO;

class ContainerFactory extends AbstractContainerFactory
{
    protected function registerPlatformServiceProviders(Container $container): void
    {
        $container->registerServiceProvider(new ConfigurationProvider())
          ->registerServiceProvider(new PsrLogger())
          ->registerServiceProvider(new PlatformProvider())
          ->registerServiceProvider(new MvcProvider())
          ->registerServiceProvider(new PluginProvider());

        if (JCH_PRO) {
            $container->registerServiceProvider(new CriticalJsConfigureHelperProvider());
        }
    }
}
