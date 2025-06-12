<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Service\Provider;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Joomla\DI\ServiceProviderInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerInterface;
use JchOptimize\Core\Admin\AdminHelper;
use JchOptimize\Core\Admin\AdminTasks;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Platform\PluginInterface;
use JchOptimize\Core\Registry;

class Admin implements ServiceProviderInterface
{
    /**
     * @inheritDoc
     */
    public function register(Container $container): void
    {
        $container->share(AdminHelper::class, function () use ($container): AdminHelper {
            return new AdminHelper($container->get(PathsInterface::class));
        });

        $container->share(AdminTasks::class, function () use ($container): AdminTasks {
            $tasks = new AdminTasks(
                $container->get(Registry::class),
                $container->get(AdminHelper::class),
                $container->get(PathsInterface::class),
                $container->get(PluginInterface::class),
            );

            $tasks->setLogger($container->get(LoggerInterface::class));
            $tasks->setContainer($container);

            return $tasks;
        });
    }
}
