<?php

namespace _JchOptimizeVendor\Laminas\Cache\Storage\Plugin;

use _JchOptimizeVendor\Laminas\EventManager\AbstractListenerAggregate;
abstract class AbstractPlugin extends AbstractListenerAggregate implements PluginInterface
{
    /** @var PluginOptions */
    protected $options;
    /**
     * Set pattern options
     *
     * @return AbstractPlugin Provides a fluent interface
     */
    public function setOptions(PluginOptions $options)
    {
        $this->options = $options;
        return $this;
    }
    /**
     * Get all pattern options
     *
     * @return PluginOptions
     */
    public function getOptions()
    {
        if (null === $this->options) {
            $this->setOptions(new PluginOptions());
        }
        return $this->options;
    }
}
