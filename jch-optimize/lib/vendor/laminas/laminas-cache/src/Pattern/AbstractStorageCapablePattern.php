<?php

declare (strict_types=1);
namespace _JchOptimizeVendor\Laminas\Cache\Pattern;

use _JchOptimizeVendor\Laminas\Cache\Storage\StorageInterface;
abstract class AbstractStorageCapablePattern extends AbstractPattern implements StorageCapableInterface
{
    /** @var StorageInterface */
    protected $storage;
    public function __construct(StorageInterface $storage, ?PatternOptions $options = null)
    {
        parent::__construct($options);
        $this->storage = $storage;
    }
    public function getStorage() : StorageInterface
    {
        return $this->storage;
    }
}
