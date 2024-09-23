<?php

namespace _JchOptimizeVendor\Laminas\Cache\Storage\Plugin;

use _JchOptimizeVendor\Laminas\EventManager\ListenerAggregateInterface;
interface PluginInterface extends ListenerAggregateInterface
{
    /**
     * Set options
     *
     * @return PluginInterface
     */
    public function setOptions(PluginOptions $options);
    /**
     * Get options
     *
     * @return PluginOptions
     */
    public function getOptions();
}
