<?php

declare(strict_types=1);

namespace _JchOptimizeVendor\V91\Laminas\Stdlib\ArrayUtils;

/**
 * Marker interface: can be used to replace keys completely in {@see ArrayUtils::merge()} operations
 */
interface MergeReplaceKeyInterface
{
    /**
     * @return mixed
     */
    public function getData();
}
