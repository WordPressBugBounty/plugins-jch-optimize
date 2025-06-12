<?php

// phpcs:disable WebimpressCodingStandard.PHP.CorrectClassNameCase.Invalid
declare(strict_types=1);

namespace _JchOptimizeVendor\V91;

use _JchOptimizeVendor\V91\Interop\Container\Containerinterface as InteropContainerInterface;
use _JchOptimizeVendor\V91\Interop\Container\Exception\ContainerException as InteropContainerException;
use _JchOptimizeVendor\V91\Interop\Container\Exception\NotFoundException as InteropNotFoundException;
use _JchOptimizeVendor\V91\Psr\Container\ContainerExceptionInterface;
use _JchOptimizeVendor\V91\Psr\Container\ContainerInterface;
use _JchOptimizeVendor\V91\Psr\Container\NotFoundExceptionInterface;

if (!\interface_exists(InteropContainerInterface::class, \false)) {
    \class_alias(ContainerInterface::class, InteropContainerInterface::class);
}
if (!\interface_exists(InteropContainerException::class, \false)) {
    \class_alias(ContainerExceptionInterface::class, InteropContainerException::class);
}
if (!\interface_exists(InteropNotFoundException::class, \false)) {
    \class_alias(NotFoundExceptionInterface::class, InteropNotFoundException::class);
}
