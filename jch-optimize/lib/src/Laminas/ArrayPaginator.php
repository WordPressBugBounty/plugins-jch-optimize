<?php

namespace JchOptimize\Core\Laminas;

use _JchOptimizeVendor\Laminas\Paginator\Adapter\ArrayAdapter;
use _JchOptimizeVendor\Laminas\Paginator\Paginator;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class ArrayPaginator extends Paginator
{
    public function __construct(array $array = [])
    {
        parent::__construct(new ArrayAdapter($array));
    }
}
