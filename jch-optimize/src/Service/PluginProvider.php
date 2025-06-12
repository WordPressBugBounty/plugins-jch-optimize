<?php

namespace JchOptimize\WordPress\Service;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Joomla\DI\ServiceProviderInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerInterface;
use JchOptimize\Core\Admin\AdminHelper;
use JchOptimize\Core\Admin\AdminTasks;
use JchOptimize\Core\Model\CacheMaintainer;
use JchOptimize\Core\PageCache\PageCache;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Platform\UtilityInterface;
use JchOptimize\Core\Registry;
use JchOptimize\WordPress\ControllerResolver;
use JchOptimize\WordPress\Plugin\Admin;
use JchOptimize\WordPress\Plugin\Installer;
use JchOptimize\WordPress\Plugin\Loader;
use JchOptimize\WordPress\Plugin\Updater;

class PluginProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->share(Loader::class, [$this, 'getLoaderService'], true);
        $container->share(Admin::class, [$this, 'getAdminService'], true);
        $container->share(Updater::class, [$this, 'getUpdaterService'], true);
        $container->share(Installer::class, [$this, 'getInstallerService'], true);
    }

    public function getLoaderService(Container $container): Loader
    {
        $loader = new Loader(
            $container->get(Registry::class),
            $container->get(Admin::class),
            $container->get(Installer::class),
            $container->get(PageCache::class),
            $container->get(UtilityInterface::class),
            $container->get(Updater::class),
        );
        $loader->setContainer($container)
               ->setLogger($container->get(LoggerInterface::class));

        return $loader;
    }

    public function getAdminService(Container $container): Admin
    {
        return new Admin(
            $container->get('params'),
            $container->get(ControllerResolver::class),
            $container->get(PathsInterface::class)
        );
    }

    public function getUpdaterService(Container $container): ?Updater
    {
        return new Updater($container->get('params'));
    }

    public function getInstallerService(Container $container): Installer
    {
        return new Installer(
            $container->get(Registry::class),
            $container->get(AdminTasks::class),
            $container->get(AdminHelper::class),
            $container->get(CacheMaintainer::class)
        );
    }
}
