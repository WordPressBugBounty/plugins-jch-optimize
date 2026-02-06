<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2025 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core;

use _JchOptimizeVendor\V91\Joomla\DI\Container as VendoredContainer;
use _JchOptimizeVendor\V91\Psr\Container\ContainerInterface as VendoredContainerInterface;
use JchOptimize\Core\Html\HtmlElementBuilder;
use JchOptimize\Core\Service\Provider\Admin;
use JchOptimize\Core\Service\Provider\Callbacks;
use JchOptimize\Core\Service\Provider\Core;
use JchOptimize\Core\Service\Provider\FeatureHelpers;
use JchOptimize\Core\Service\Provider\LaminasCache;
use JchOptimize\Core\Service\Provider\SharedEvents;
use JchOptimize\Core\Service\Provider\Spatie;
use Joomla\DI\Container as JoomlaContainer;
use Psr\Container\ContainerInterface as PsrContainerInterface;

abstract class AbstractContainerFactory
{
    private static ?VendoredContainer $container = null;

    final public function __construct()
    {
    }

    /**
     * Will return a new instance of the container every time
     */
    public static function create(
        VendoredContainerInterface|PsrContainerInterface|null $parent = null
    ): VendoredContainer {
        $ContainerFactory = new static();

        $container = new VendoredContainer($parent);

        $ContainerFactory->registerCoreServiceProviders($container);
        $ContainerFactory->registerPlatformServiceProviders($container);

        Debugger::setContainer($container);
        HtmlElementBuilder::setContainer($container);

        return $container;
    }

    /**
     * @param   VendoredContainer  $container
     *
     * @return void
     */
    public function registerCoreServiceProviders(VendoredContainer $container): void
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

    public static function resetContainer(
        VendoredContainer|JoomlaContainer $container
    ): VendoredContainer|JoomlaContainer {
        foreach ((clone $container)->getKeys() as $key) {
            if ($resource = $container->getResource($key)) {
                $resource->reset();
            }
        }

        return $container;
    }

    public static function getInstance(
        VendoredContainerInterface|PsrContainerInterface|null $parent = null
    ): VendoredContainer {
        if (null === self::$container) {
            self::$container = self::create($parent);
        }

        return self::$container;
    }

    /**
     * To be implemented by JchOptimize/Container to attach service providers specific to the particular platform
     *
     * @param   VendoredContainer  $container
     *
     * @return void
     */
    abstract protected function registerPlatformServiceProviders(VendoredContainer $container): void;
}
