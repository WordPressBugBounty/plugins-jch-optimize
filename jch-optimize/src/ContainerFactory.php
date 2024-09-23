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

namespace JchOptimize;

use JchOptimize\Core\Container\Container;
use JchOptimize\Core\Service\LoggerProvider;
use JchOptimize\Service\ConfigurationProvider;
use JchOptimize\Service\MvcProvider;
use JchOptimize\Service\PluginProvider;

class ContainerFactory extends Core\Container\AbstractContainerFactory
{
    public function registerPlatformProviders(Container $container): void
    {
        $container->registerServiceProvider(new ConfigurationProvider())
                  ->registerServiceProvider(new LoggerProvider())
                  ->registerServiceProvider(new MvcProvider())
                  ->registerServiceProvider(new PluginProvider());
    }
}
