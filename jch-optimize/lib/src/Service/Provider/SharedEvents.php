<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Service\Provider;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Joomla\DI\ServiceProviderInterface;
use _JchOptimizeVendor\V91\Laminas\EventManager\SharedEventManager;
use _JchOptimizeVendor\V91\Laminas\EventManager\SharedEventManagerInterface;

class SharedEvents implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->alias(SharedEventManager::class, SharedEventManagerInterface::class)
            ->share(SharedEventManagerInterface::class, new SharedEventManager());
    }
}
