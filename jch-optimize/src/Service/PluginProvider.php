<?php

namespace JchOptimize\Service;

use JchOptimize\ControllerResolver;
use JchOptimize\Core\Container\Container;
use JchOptimize\Core\Container\ServiceProviderInterface;
use JchOptimize\Core\Optimize;
use JchOptimize\Core\PageCache\PageCache;
use JchOptimize\Plugin\Admin;
use JchOptimize\Plugin\Installer;
use JchOptimize\Plugin\Loader;
use JchOptimize\Plugin\Updater;

class PluginProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container->share(Loader::class, [$this, 'getLoaderService'], true);
        $container->share(Admin::class, [$this, 'getAdminService'], true);
        $container->share(Updater::class, [$this, 'getUpdaterService'], true);
        $container->share(Installer::class, [$this, 'getInstallerService'], true);
    }

    public function getLoaderService(Container $container): Loader
    {
        $loader = new Loader(
            $container->get('params'),
            $container->get(Admin::class),
            $container->get(Installer::class),
            $container->get(Updater::class),
            $container->get(Optimize::class),
            $container->get(PageCache::class)
        );
        $loader->setContainer($container)
               ->setLogger($container->get('logger'));

        return $loader;
    }

    public function getAdminService(Container $container): Admin
    {
        return new Admin(
            $container->get('params'),
            $container->get(ControllerResolver::class)
        );
    }

    public function getUpdaterService(Container $container): ?Updater
    {
        if (JCH_PRO) {
            return new Updater(
                $container->get('params')
            );
        } else {
            return null;
        }
    }

    public function getInstallerService(): Installer
    {
        return new Installer();
    }
}
