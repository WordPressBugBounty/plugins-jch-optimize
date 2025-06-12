<?php

declare(strict_types=1);

namespace _JchOptimizeVendor\V91\Laminas\ServiceManager\Exception;

use InvalidArgumentException as SplInvalidArgumentException;
use _JchOptimizeVendor\V91\Psr\Container\NotFoundExceptionInterface;

/**
 * This exception is thrown when the service locator do not manage to find a
 * valid factory to create a service
 */
class ServiceNotFoundException extends SplInvalidArgumentException implements ExceptionInterface, NotFoundExceptionInterface
{
}
