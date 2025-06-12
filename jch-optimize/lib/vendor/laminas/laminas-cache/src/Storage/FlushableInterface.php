<?php

namespace _JchOptimizeVendor\V91\Laminas\Cache\Storage;

interface FlushableInterface
{
    /**
     * Flush the whole storage
     *
     * @return bool
     */
    public function flush();
}
