<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads.
 *
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Service;

use _JchOptimizeVendor\Joomla\DI\Container;
use _JchOptimizeVendor\Joomla\DI\ServiceProviderInterface;
use _JchOptimizeVendor\Laminas\Cache\Storage\Adapter\Apcu;
use _JchOptimizeVendor\Laminas\Cache\Storage\Adapter\BlackHole;
use _JchOptimizeVendor\Laminas\Cache\Storage\Adapter\Filesystem;
use _JchOptimizeVendor\Laminas\Cache\Storage\Adapter\Memcached;
use _JchOptimizeVendor\Laminas\Cache\Storage\Adapter\Redis;
use _JchOptimizeVendor\Laminas\ServiceManager\Factory\InvokableFactory;
use JchOptimize\Core\Laminas\Plugins\ExceptionHandler;
use JchOptimize\Platform\Paths;

\defined('_JCH_EXEC') or exit('Restricted access');
class CachingConfigurationProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container->share('config', function ($container) {
            $params = $container->get('params');
            $dirPermission = \octdec(\substr(\sprintf('%o', \fileperms(__DIR__)), -4)) ?: 0755;
            $filePermission = \octdec(\substr(\sprintf('%o', \fileperms(__FILE__)), -4)) ?: 0644;
            // Ensure owner has permissions to execute, read, and write directory
            $dirPermission = $dirPermission | 0700;
            // Ensure owner has permissions to read and write files
            $filePermission = $filePermission | 0600;
            // Ensure files are not executable
            $filePermission = $filePermission & ~0111;
            $redisServerHost = (string) $params->get('redis_server_host', '127.0.0.1');
            if ('.sock' == \substr(\trim($redisServerHost), -5)) {
                $redisServer = $redisServerHost;
            } else {
                $redisServer = ['host' => $redisServerHost, 'port' => (int) $params->get('redis_server_port', 6379)];
            }

            return ['caches' => ['filesystem' => ['name' => 'filesystem', 'options' => ['cache_dir' => Paths::cacheDir(), 'dir_level' => 2, 'dir_permission' => $dirPermission, 'file_permission' => $filePermission], 'plugins' => [['name' => 'serializer'], ['name' => 'exception_handler', 'options' => ['exception_callback' => [ExceptionHandler::class, 'logException'], 'throw_exceptions' => \false]]]], 'memcached' => ['name' => 'memcached', 'options' => ['servers' => [[(string) $params->get('memcached_server_host', '127.0.0.1'), (int) $params->get('memcached_server_port', 11211)]]], 'plugins' => [['name' => 'exception_handler', 'options' => ['exception_callback' => [ExceptionHandler::class, 'logException'], 'throw_exceptions' => \false]]]], 'apcu' => ['name' => 'apcu', 'options' => [], 'plugins' => [['name' => 'exception_handler', 'options' => ['exception_callback' => [ExceptionHandler::class, 'logException'], 'throw_exceptions' => \false]]]], 'redis' => ['name' => 'redis', 'options' => ['server' => $redisServer, 'password' => (string) $params->get('redis_server_password', ''), 'database' => (int) $params->get('redis_server_db', 0)], 'plugins' => [['name' => 'serializer'], ['name' => 'exception_handler', 'options' => ['exception_callback' => [ExceptionHandler::class, 'logException'], 'throw_exceptions' => \false]]]], 'blackhole' => ['name' => 'blackhole', 'options' => [], 'plugins' => [['name' => 'exception_handler', 'options' => ['exception_callback' => [ExceptionHandler::class, 'logException'], 'throw_exceptions' => \false]]]]], 'dependencies' => ['factories' => [Filesystem::class => InvokableFactory::class, Memcached::class => InvokableFactory::class, Apcu::class => InvokableFactory::class, Redis::class => InvokableFactory::class, BlackHole::class => InvokableFactory::class], 'aliases' => ['filesystem' => Filesystem::class, 'memcached' => Memcached::class, 'apcu' => Apcu::class, 'redis' => Redis::class, 'blackhole' => BlackHole::class]]];
        }, \true);
    }
}
