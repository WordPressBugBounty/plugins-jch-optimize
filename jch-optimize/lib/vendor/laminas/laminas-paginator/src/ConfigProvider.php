<?php

namespace _JchOptimizeVendor\V91\Laminas\Paginator;

class ConfigProvider
{
    /**
     * Retrieve default laminas-paginator configuration.
     *
     * @return array
     */
    public function __invoke()
    {
        return ['dependencies' => $this->getDependencyConfig()];
    }
    /**
     * Retrieve dependency configuration for laminas-paginator.
     *
     * @return array
     */
    public function getDependencyConfig()
    {
        return [
            // Legacy Zend Framework aliases
            'aliases' => [\_JchOptimizeVendor\V91\Zend\Paginator\AdapterPluginManager::class => AdapterPluginManager::class, \_JchOptimizeVendor\V91\Zend\Paginator\ScrollingStylePluginManager::class => ScrollingStylePluginManager::class],
            'factories' => [AdapterPluginManager::class => AdapterPluginManagerFactory::class, ScrollingStylePluginManager::class => ScrollingStylePluginManagerFactory::class],
        ];
    }
}
