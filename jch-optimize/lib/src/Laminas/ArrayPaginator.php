<?php

namespace JchOptimize\Core\Laminas;

use _JchOptimizeVendor\V91\Laminas\Paginator\Adapter\ArrayAdapter;
use _JchOptimizeVendor\V91\Laminas\Paginator\Paginator;

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
