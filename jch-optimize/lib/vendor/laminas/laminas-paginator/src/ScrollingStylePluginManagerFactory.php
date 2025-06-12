<?php

namespace _JchOptimizeVendor\V91\Laminas\Paginator;

use _JchOptimizeVendor\V91\Interop\Container\ContainerInterface;
use _JchOptimizeVendor\V91\Laminas\ServiceManager\FactoryInterface;
use _JchOptimizeVendor\V91\Laminas\ServiceManager\ServiceLocatorInterface;

class ScrollingStylePluginManagerFactory implements FactoryInterface
{
    /**
     * laminas-servicemanager v2 support for invocation options.
     *
     * @var array
     */
    protected $creationOptions;
    /**
     * {@inheritDoc}
     *
     * @return ScrollingStylePluginManager
     */
    public function __invoke(ContainerInterface $container, $name, ?array $options = null)
    {
        return new ScrollingStylePluginManager($container, $options ?: []);
    }
    /**
     * {@inheritDoc}
     *
     * @return ScrollingStylePluginManager
     */
    public function createService(ServiceLocatorInterface $container, $name = null, $requestedName = null)
    {
        return $this($container, $requestedName ?: ScrollingStylePluginManager::class, $this->creationOptions);
    }
    /**
     * laminas-servicemanager v2 support for invocation options.
     *
     * @param array $options
     * @return void
     */
    public function setCreationOptions(array $options)
    {
        $this->creationOptions = $options;
    }
}
