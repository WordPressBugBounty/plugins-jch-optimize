<?php

declare (strict_types=1);
namespace _JchOptimizeVendor\Laminas\ServiceManager\Factory;

use _JchOptimizeVendor\Psr\Container\ContainerInterface;
/**
 * Factory for instantiating classes with no dependencies or which accept a single array.
 *
 * The InvokableFactory can be used for any class that:
 *
 * - has no constructor arguments;
 * - accepts a single array of arguments via the constructor.
 *
 * It replaces the "invokables" and "invokable class" functionality of the v2
 * service manager.
 */
final class InvokableFactory implements FactoryInterface
{
    /** {@inheritDoc} */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        return null === $options ? new $requestedName() : new $requestedName($options);
    }
}
