<?php

declare (strict_types=1);
namespace _JchOptimizeVendor\Laminas\Cache\Pattern;

use _JchOptimizeVendor\Laminas\Cache\Storage\StorageInterface;
interface StorageCapableInterface extends PatternInterface
{
    public function getStorage() : ?StorageInterface;
}
