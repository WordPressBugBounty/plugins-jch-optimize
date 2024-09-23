<?php

namespace _JchOptimizeVendor\Laminas\Paginator;

class Module
{
    /**
     * Retrieve default laminas-paginator config for laminas-mvc context.
     *
     * @return array
     */
    public function getConfig()
    {
        $provider = new ConfigProvider();
        return ['service_manager' => $provider->getDependencyConfig()];
    }
}
