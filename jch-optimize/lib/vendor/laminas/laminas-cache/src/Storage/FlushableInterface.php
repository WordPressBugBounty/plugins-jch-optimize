<?php

namespace _JchOptimizeVendor\Laminas\Cache\Storage;

interface FlushableInterface
{
    /**
     * Flush the whole storage
     *
     * @return bool
     */
    public function flush();
}
