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

namespace JchOptimize\Core\Laminas;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use JchOptimize\Core\Service\Provider\LaminasCacheServices;

class CacheConfigurationContainerFactory
{
    private static ?Container $container = null;

    public static function create(Container $parent): Container
    {
        if (self::$container === null) {
            self::$container = $parent->createChild();

            self::$container->registerServiceProvider(new LaminasCacheServices());
        }

        return self::$container;
    }
}
