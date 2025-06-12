<?php

namespace _JchOptimizeVendor\V91\Laminas\Cache\Storage;

interface ClearByNamespaceInterface
{
    /**
     * Remove items of given namespace
     *
     * @param string $namespace
     * @return bool
     */
    public function clearByNamespace($namespace);
}
