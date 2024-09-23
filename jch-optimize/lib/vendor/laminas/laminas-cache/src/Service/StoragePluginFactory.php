<?php

declare (strict_types=1);
namespace _JchOptimizeVendor\Laminas\Cache\Service;

use InvalidArgumentException as PhpInvalidArgumentException;
use _JchOptimizeVendor\Laminas\Cache\Exception\InvalidArgumentException;
use _JchOptimizeVendor\Laminas\Cache\Storage\Plugin\PluginInterface;
use _JchOptimizeVendor\Laminas\ServiceManager\PluginManagerInterface;
use _JchOptimizeVendor\Webmozart\Assert\Assert;
use function assert;
final class StoragePluginFactory implements StoragePluginFactoryInterface
{
    /** @var PluginManagerInterface */
    private $plugins;
    public function __construct(PluginManagerInterface $plugins)
    {
        $this->plugins = $plugins;
    }
    public function createFromArrayConfiguration(array $configuration) : PluginInterface
    {
        $name = $configuration['name'];
        $options = $configuration['options'] ?? [];
        return $this->create($name, $options);
    }
    public function create(string $plugin, array $options = []) : PluginInterface
    {
        $instance = $this->plugins->build($plugin, $options);
        assert($instance instanceof PluginInterface);
        return $instance;
    }
    public function assertValidConfigurationStructure(array $configuration) : void
    {
        try {
            Assert::isNonEmptyMap($configuration, 'Configuration must be a non-empty array.');
            Assert::keyExists($configuration, 'name', 'Configuration must contain a "name" key.');
            Assert::stringNotEmpty($configuration['name'], 'Plugin "name" has to be a non-empty string.');
            Assert::nullOrIsMap($configuration['options'] ?? null, 'Plugin "options" must be an array with string keys.');
        } catch (PhpInvalidArgumentException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
        }
    }
}
