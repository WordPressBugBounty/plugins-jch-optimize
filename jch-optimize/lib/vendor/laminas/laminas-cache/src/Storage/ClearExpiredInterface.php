<?php

namespace _JchOptimizeVendor\V91\Laminas\Cache\Storage;

interface ClearExpiredInterface
{
    /**
     * Remove expired items
     *
     * @return bool
     */
    public function clearExpired();
}
