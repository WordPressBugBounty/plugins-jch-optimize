<?php

namespace _JchOptimizeVendor\Laminas\Cache;

use _JchOptimizeVendor\Laminas\Cache\Command\DeprecatedStorageFactoryConfigurationCheckCommand;
use _JchOptimizeVendor\Laminas\Cache\Command\DeprecatedStorageFactoryConfigurationCheckCommandFactory;
use _JchOptimizeVendor\Laminas\Cache\Service\StorageAdapterFactory;
use _JchOptimizeVendor\Laminas\Cache\Service\StorageAdapterFactoryFactory;
use _JchOptimizeVendor\Laminas\Cache\Service\StorageAdapterFactoryInterface;
use _JchOptimizeVendor\Laminas\Cache\Service\StoragePluginFactory;
use _JchOptimizeVendor\Laminas\Cache\Service\StoragePluginFactoryFactory;
use _JchOptimizeVendor\Laminas\Cache\Service\StoragePluginFactoryInterface;
use _JchOptimizeVendor\Symfony\Component\Console\Command\Command;
use function class_exists;
class ConfigProvider
{
    /**
     * Return default configuration for laminas-cache.
     *
     * @return array
     */
    public function __invoke()
    {
        return ['dependencies' => $this->getDependencyConfig(), 'laminas-cli' => $this->getCliConfig()];
    }
    /**
     * Return default service mappings for laminas-cache.
     *
     * @return array
     */
    public function getDependencyConfig()
    {
        $dependencies = ['abstract_factories' => [Service\StorageCacheAbstractServiceFactory::class], 'factories' => [Storage\AdapterPluginManager::class => Service\StorageAdapterPluginManagerFactory::class, Storage\PluginManager::class => Service\StoragePluginManagerFactory::class, StoragePluginFactory::class => StoragePluginFactoryFactory::class, StoragePluginFactoryInterface::class => StoragePluginFactoryFactory::class, StorageAdapterFactory::class => StorageAdapterFactoryFactory::class, StorageAdapterFactoryInterface::class => StorageAdapterFactoryFactory::class]];
        if (class_exists(Command::class)) {
            $dependencies['factories'] += [DeprecatedStorageFactoryConfigurationCheckCommand::class => DeprecatedStorageFactoryConfigurationCheckCommandFactory::class];
        }
        return $dependencies;
    }
    /**
     * @return array<string,mixed>
     */
    public function getCliConfig() : array
    {
        if (!class_exists(Command::class)) {
            return [];
        }
        return ['commands' => [DeprecatedStorageFactoryConfigurationCheckCommand::NAME => DeprecatedStorageFactoryConfigurationCheckCommand::class]];
    }
}
