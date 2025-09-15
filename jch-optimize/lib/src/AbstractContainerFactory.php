<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2025 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Psr\Container\ContainerInterface;
use JchOptimize\Core\Html\HtmlElementBuilder;
use JchOptimize\Core\Service\Provider\Admin;
use JchOptimize\Core\Service\Provider\Callbacks;
use JchOptimize\Core\Service\Provider\Core;
use JchOptimize\Core\Service\Provider\FeatureHelpers;
use JchOptimize\Core\Service\Provider\LaminasCache;
use JchOptimize\Core\Service\Provider\SharedEvents;
use JchOptimize\Core\Service\Provider\Spatie;

abstract class AbstractContainerFactory
{
    private static ?Container $container = null;

    final public function __construct()
    {
    }

    /**
     * Will return a new instance of the container every time
     */
    public static function create(ContainerInterface|\Psr\Container\ContainerInterface|null $parent = null): Container
    {
        $ContainerFactory = new static();

        $container = new Container($parent);

        $ContainerFactory->registerCoreServiceProviders($container);
        $ContainerFactory->registerPlatformServiceProviders($container);

        Debugger::setContainer($container);
        HtmlElementBuilder::setContainer($container);

        return $container;
    }

    /**
     * @param Container $container
     *
     * @return void
     */
    public function registerCoreServiceProviders(Container $container): void
    {
        $container->registerServiceProvider(new SharedEvents())
            ->registerServiceProvider(new Core())
            ->registerServiceProvider(new Callbacks())
            ->registerServiceProvider(new LaminasCache())
            ->registerServiceProvider(new Admin());

        if (JCH_PRO) {
            $container->registerServiceProvider(new FeatureHelpers())
                ->registerServiceProvider(new Spatie());
        }
    }

    public static function resetContainer(Container|\Joomla\DI\Container $container): Container|\Joomla\DI\Container
    {
        foreach ((clone $container)->getKeys() as $key) {
            if ($resource = $container->getResource($key)) {
                $resource->reset();
            }
        }

        return $container;
    }

    public static function getInstance(
        ContainerInterface|\Psr\Container\ContainerInterface|null $parent = null
    ): Container {
        if (null === self::$container) {
            self::$container = self::create($parent);
        }

        return self::$container;
    }

    /**
     * To be implemented by JchOptimize/Container to attach service providers specific to the particular platform
     *
     * @param Container $container
     *
     * @return void
     */
    abstract protected function registerPlatformServiceProviders(Container $container): void;
}
