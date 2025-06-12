<?php

namespace _JchOptimizeVendor\V91\Laminas\Cache\Storage;

use _JchOptimizeVendor\V91\Laminas\EventManager\EventsCapableInterface;
use SplObjectStorage;

interface PluginCapableInterface extends EventsCapableInterface
{
    /**
     * Check if a plugin is registered
     *
     * @return bool
     */
    public function hasPlugin(Plugin\PluginInterface $plugin);
    /**
     * Return registry of plugins
     *
     * @return SplObjectStorage
     */
    public function getPluginRegistry();
}
