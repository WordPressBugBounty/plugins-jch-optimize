<?php

namespace JchOptimize\Core\Container;

use _JchOptimizeVendor\Joomla\DI\Container;
use _JchOptimizeVendor\Joomla\DI\Exception\ContainerNotFoundException;

trait ContainerAwareTrait
{
    private ?Container $container = null;

    public function setContainer(Container $container): static
    {
        $this->container = $container;

        return $this;
    }

    protected function getContainer(): Container
    {
        if ($this->container instanceof Container) {
            return $this->container;
        }

        throw new ContainerNotFoundException('Container not set in '.\get_class($this));
    }
}
