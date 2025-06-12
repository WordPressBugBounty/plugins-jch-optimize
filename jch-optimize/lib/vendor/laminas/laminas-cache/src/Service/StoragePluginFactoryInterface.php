<?php

declare(strict_types=1);

namespace _JchOptimizeVendor\V91\Laminas\Cache\Service;

use _JchOptimizeVendor\V91\Laminas\Cache\Exception\InvalidArgumentException;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\Plugin\PluginInterface;

/**
 * @psalm-type PluginArrayConfigurationType = array{name:non-empty-string,options?:array<string,mixed>}
 */
interface StoragePluginFactoryInterface
{
    /**
     * @psalm-param PluginArrayConfigurationType $configuration
     */
    public function createFromArrayConfiguration(array $configuration): PluginInterface;
    /**
     * @psalm-param non-empty-string $plugin
     * @param array<string,mixed>  $options
     * @psalm-param array<string,mixed> $options
     */
    public function create(string $plugin, array $options = []): PluginInterface;
    /**
     * @param array<mixed> $configuration
     * @psalm-assert PluginArrayConfigurationType $configuration
     * @throws InvalidArgumentException If the provided configuration is invalid.
     */
    public function assertValidConfigurationStructure(array $configuration): void;
}
