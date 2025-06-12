<?php

declare(strict_types=1);

namespace _JchOptimizeVendor\V91\Laminas\Cache\Pattern;

use _JchOptimizeVendor\V91\Laminas\Cache\Storage\StorageInterface;

interface StorageCapableInterface extends PatternInterface
{
    public function getStorage(): ?StorageInterface;
}
