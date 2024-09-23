<?php

// phpcs:disable WebimpressCodingStandard.PHP.CorrectClassNameCase.Invalid
declare (strict_types=1);
namespace _JchOptimizeVendor;

use _JchOptimizeVendor\Interop\Container\Containerinterface as InteropContainerInterface;
use _JchOptimizeVendor\Interop\Container\Exception\ContainerException as InteropContainerException;
use _JchOptimizeVendor\Interop\Container\Exception\NotFoundException as InteropNotFoundException;
use _JchOptimizeVendor\Psr\Container\ContainerExceptionInterface;
use _JchOptimizeVendor\Psr\Container\ContainerInterface;
use _JchOptimizeVendor\Psr\Container\NotFoundExceptionInterface;
if (!\interface_exists(InteropContainerInterface::class, \false)) {
    \class_alias(ContainerInterface::class, InteropContainerInterface::class);
}
if (!\interface_exists(InteropContainerException::class, \false)) {
    \class_alias(ContainerExceptionInterface::class, InteropContainerException::class);
}
if (!\interface_exists(InteropNotFoundException::class, \false)) {
    \class_alias(NotFoundExceptionInterface::class, InteropNotFoundException::class);
}
