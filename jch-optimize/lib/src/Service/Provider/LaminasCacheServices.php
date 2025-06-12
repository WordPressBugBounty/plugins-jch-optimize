<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Service\Provider;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Joomla\DI\ServiceProviderInterface;
use _JchOptimizeVendor\V91\Laminas\Cache\Service\StorageAdapterFactory;
use _JchOptimizeVendor\V91\Laminas\Cache\Service\StorageAdapterFactoryInterface;
use _JchOptimizeVendor\V91\Laminas\Cache\Service\StoragePluginFactory;
use _JchOptimizeVendor\V91\Laminas\Cache\Service\StoragePluginFactoryInterface;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\Adapter\Apcu;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\Adapter\BlackHole;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\Adapter\Filesystem;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\Adapter\Memcached;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\Adapter\Redis;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\AdapterPluginManager;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\PluginManager;
use _JchOptimizeVendor\V91\Laminas\ServiceManager\Factory\InvokableFactory;
use _JchOptimizeVendor\V91\Laminas\ServiceManager\PluginManagerInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerInterface;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Registry;

use function defined;
use function fileperms;
use function octdec;
use function sprintf;
use function substr;

defined('_JCH_EXEC') or die('Restricted access');

class LaminasCacheServices implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->alias(StorageAdapterFactoryInterface::class, StorageAdapterFactory::class)
            ->share(StorageAdapterFactory::class, [$this, 'getStorageAdapterFactoryService'], true);

        $container->alias(PluginManagerInterface::class, AdapterPluginManager::class)
            ->share(AdapterPluginManager::class, [$this, 'getAdapterPluginManagerService'], true);

        $container->alias(StoragePluginFactoryInterface::class, StoragePluginFactory::class)
            ->share(StoragePluginFactory::class, [$this, 'getStoragePluginFactoryService'], true);

        $container->share(PluginManager::class, [$this, 'getPluginManagerService'], true);

        $container->share('config', [$this, 'getConfigurationService'], true);
    }

    public function getStorageAdapterFactoryService(Container $container): StorageAdapterFactoryInterface
    {
        return new StorageAdapterFactory(
            $container->get(PluginManagerInterface::class),
            $container->get(StoragePluginFactoryInterface::class)
        );
    }

    public function getAdapterPluginManagerService(Container $container): PluginManagerInterface
    {
        return new AdapterPluginManager(
            $container,
            $container->get('config')['dependencies']
        );
    }

    public function getStoragePluginFactoryService(Container $container): StoragePluginFactoryInterface
    {
        return new StoragePluginFactory($container->get(PluginManager::class));
    }

    public function getPluginManagerService(Container $container): PluginManagerInterface
    {
        return new PluginManager(
            $container,
            $container->get('config')['dependencies']
        );
    }

    public function getConfigurationService(Container $container): array
    {
        $dirPermission = octdec(substr(sprintf('%o', fileperms(__DIR__)), -4)) ?: 0755;
        $filePermission = octdec(substr(sprintf('%o', fileperms(__FILE__)), -4)) ?: 0644;

        //Ensure owner has permissions to execute, read, and write directory
        $dirPermission = ($dirPermission | 0700);
        //Ensure owner has permissions to read and write files
        $filePermission = ($filePermission | 0600);
        //Ensure files are not executable
        $filePermission = ($filePermission & ~0111);

        $params = $container->get(Registry::class);
        $paths = $container->get(PathsInterface::class);
        $logger = $container->get(LoggerInterface::class);

        $redisServerHost = (string) $params->get('redis_server_host', '127.0.0.1');

        if (str_ends_with(trim($redisServerHost), '.sock')) {
            $redisServer = $redisServerHost;
        } else {
            $redisServer = [
                'host' => $redisServerHost,
                'port' => (int)$params->get('redis_server_port', 6379)
            ];
        }

        return [
            'caches' => [
                 'filesystem' => [
                     'name' => 'filesystem',
                     'options' => [
                         'cache_dir'       => $paths->cacheDir(),
                         'dir_level'       => 2,
                         'dir_permission'  => $dirPermission,
                         'file_permission' => $filePermission
                     ],
                     'plugins' => [
                         [
                             'name' => 'serializer'
                         ],
                         [
                             'name' => 'exception_handler',
                             'options' => [
                                'exception_callback' => [$logger, 'error'],
                                'throw_exceptions' => false
                             ]
                         ]
                     ]
                 ],
                     'memcached' => [
                         'name' => 'memcached',
                         'options' => [
                     'servers' => [
                         [ (string)$params->get(
                             'memcached_server_host',
                             '127.0.0.1'
                         ),
                           (int)$params->get('memcached_server_port', 11211)
                         ]
                     ]
                         ],
                         'plugins' => [
                             [
                                 'name' => 'exception_handler',
                                 'options' => [
                                     'exception_callback' => [$logger, 'error'],
                                     'throw_exceptions' => false
                                 ]
                             ]
                         ]
                 ],
                 'apcu' => [
                     'name' => 'apcu',
                     'options' => [],
                     'plugins' => [
                         [
                         'name' => 'exception_handler',
                         'options' => [
                             'exception_callback' => [$logger, 'error'],
                             'throw_exceptions' => false
                             ]
                         ]
                     ]
                 ],
                 'redis' => [
                     'name' => 'redis',
                     'options' => [
                         'server' => $redisServer,
                         'password' => (string)$params->get('redis_server_password', ''),
                         'database' => (int)$params->get('redis_server_db', 0)
                     ],
                     'plugins' => [
                         [
                             'name' => 'serializer'
                         ],
                         [
                             'name' => 'exception_handler',
                             'options' => [
                                 'exception_callback' => [$logger, 'error'],
                                 'throw_exceptions' => false
                             ]
                         ]
                     ]
                 ],
                 'blackhole' => [
                 'name' => 'blackhole',
                 'options' => [],
                 'plugins' => [
                     [
                         'name' => 'exception_handler',
                         'options' => [
                             'exception_callback' => [$logger, 'error'],
                             'throw_exceptions' => false
                             ]
                         ]
                     ]
                 ]

            ],
            'dependencies' => [
                'factories' => [
                    Filesystem::class => InvokableFactory::class,
                    Memcached::class => InvokableFactory::class,
                    Apcu::class => InvokableFactory::class,
                    Redis::class => InvokableFactory::class,
                    BlackHole::class => InvokableFactory::class
                ],
                'aliases' => [
                    'filesystem' => Filesystem::class,
                    'memcached' => Memcached::class,
                    'apcu' => Apcu::class,
                    'redis' => Redis::class,
                    'blackhole' => BlackHole::class
                ],
            ]
        ];
    }
}
