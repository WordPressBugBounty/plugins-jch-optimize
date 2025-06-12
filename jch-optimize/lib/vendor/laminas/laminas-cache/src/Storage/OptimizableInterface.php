<?php

namespace _JchOptimizeVendor\V91\Laminas\Cache\Storage;

interface OptimizableInterface
{
    /**
     * Optimize the storage
     *
     * @return bool
     */
    public function optimize();
}
