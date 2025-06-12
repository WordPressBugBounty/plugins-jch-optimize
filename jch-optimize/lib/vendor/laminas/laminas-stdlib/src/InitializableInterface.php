<?php

declare(strict_types=1);

namespace _JchOptimizeVendor\V91\Laminas\Stdlib;

/**
 * Interface to allow objects to have initialization logic
 */
interface InitializableInterface
{
    /**
     * Init an object
     *
     * @return void
     */
    public function init();
}
