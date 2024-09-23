<?php

namespace _JchOptimizeVendor\Laminas\Cache\Storage;

use _JchOptimizeVendor\Laminas\Cache\Exception;
interface PluginAwareInterface extends PluginCapableInterface
{
    /**
     * Register a plugin
     *
     * @param  int $priority
     * @return StorageInterface
     * @throws Exception\LogicException
     */
    public function addPlugin(Plugin\PluginInterface $plugin, $priority = 1);
    /**
     * Unregister an already registered plugin
     *
     * @return StorageInterface
     * @throws Exception\LogicException
     */
    public function removePlugin(Plugin\PluginInterface $plugin);
}
