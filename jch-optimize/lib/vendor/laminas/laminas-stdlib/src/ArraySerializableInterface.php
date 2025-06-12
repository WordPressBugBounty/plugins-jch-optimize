<?php

declare(strict_types=1);

namespace _JchOptimizeVendor\V91\Laminas\Stdlib;

interface ArraySerializableInterface
{
    /**
     * Exchange internal values from provided array
     *
     * @return void
     */
    public function exchangeArray(array $array);
    /**
     * Return an array representation of the object
     *
     * @return array
     */
    public function getArrayCopy();
}
