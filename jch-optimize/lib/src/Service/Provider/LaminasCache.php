<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Service\Provider;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Joomla\DI\ServiceProviderInterface;
use _JchOptimizeVendor\V91\Joomla\Filesystem\File;
use _JchOptimizeVendor\V91\Laminas\Cache\Pattern\CallbackCache;
use _JchOptimizeVendor\V91\Laminas\Cache\Pattern\CaptureCache;
use _JchOptimizeVendor\V91\Laminas\Cache\Pattern\PatternOptions;
use _JchOptimizeVendor\V91\Laminas\Cache\Service\StorageCacheAbstractServiceFactory;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\Adapter\Apcu;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\Adapter\BlackHole;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\Adapter\Filesystem;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\Adapter\Memcached;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\Adapter\Redis;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\IterableInterface;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\PluginAwareInterface;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\StorageInterface;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\TaggableInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerInterface;
use JchOptimize\Core\Exception;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Laminas\CacheConfigurationContainerFactory;
use JchOptimize\Core\Laminas\Plugins\ClearExpiredByFactor;
use JchOptimize\Core\Platform\CacheInterface;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Platform\UtilityInterface;
use JchOptimize\Core\Registry;
use Throwable;

use function defined;
use function file_exists;
use function max;
use function md5;

defined('_JCH_EXEC') or die('Restricted access');

class LaminasCache implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->share(StorageInterface::class, [$this, 'getStorageInterfaceService']);
        $container->share(CallbackCache::class, [$this, 'getCallbackCacheService']);
        $container->share(CaptureCache::class, [$this, 'getCaptureCacheService']);
        $container->share('page_cache', [$this, 'getPageCacheStorageService']);

        $container->alias('Filesystem', Filesystem::class)
            ->share(Filesystem::class, [$this, 'getFilesystemService']);
        $container->alias('Redis', Redis::class)
            ->share(Redis::class, [$this, 'getRedisService']);
        $container->alias('Apcu', Apcu::class)
            ->share(Apcu::class, [$this, 'getApcuService']);
        $container->alias('Memcached', Memcached::class)
            ->share(Memcached::class, [$this, 'getMemcachedService']);

        $container->share(TaggableInterface::class, [$this, 'getTaggableInterfaceService']);
        $container->share(ClearExpiredByFactor::class, [$this, 'getClearExpiredByFactorService']);
    }

    /**
     * This will always fetch the Filesystem storage adapter
     *
     * @throws Exception\RuntimeException
     */
    public function getFilesystemService(Container $container): StorageInterface
    {
        $fsCache = $this->getCacheAdapter($container, 'filesystem');
        $fsCache->getOptions()->setTtl(0);

        return $fsCache;
    }

    private function getCacheAdapter(Container $container, string $adapter): StorageInterface
    {
        if ($adapter == 'filesystem') {
            Helper::createCacheFolder($container);
        }

        $laminasContainer = CacheConfigurationContainerFactory::create($container);

        try {
            //if adapter is blackhole we create that manually
            if ($adapter == 'blackhole') {
                $cache = new BlackHole();
            } else {
                $factory = new StorageCacheAbstractServiceFactory();
                /** @var StorageInterface $cache */
                $cache = $factory($laminasContainer, $adapter);
                $cache->getOptions()
                    ->setNamespace($container->get(CacheInterface::class)->getGlobalCacheNamespace());
                //Let's make sure we can connect
                $cache->addItem(md5('__ITEM__'), '__ITEM__');
            }

            return $cache;
        } catch (Throwable $e) {
            $logger = $container->get(LoggerInterface::class);
            $message = 'Error in JCH Optimize retrieving configured storage adapter with message: ' . $e->getMessage();

            if ($adapter != 'filesystem') {
                $message .= ': Using the filesystem storage instead';
            }

            $logger->error($message);

            $container->get(UtilityInterface::class)->publishAdminMessages($message, 'error');

            if ($adapter != 'filesystem') {
                return $this->getCacheAdapter($container, 'filesystem');
            }

            throw new Exception\RuntimeException($message);
        }
    }

    /**
     * @throws Exception\RuntimeException
     */
    public function getRedisService(Container $container): StorageInterface
    {
        $redisCache = $this->getCacheAdapter($container, 'redis');
        $redisCache->getOptions()->setTtl(0);

        return $redisCache;
    }

    /**
     * @throws Exception\RuntimeException
     */
    public function getApcuService(Container $container): StorageInterface
    {
        $apcuCache = $this->getCacheAdapter($container, 'apcu');
        $apcuCache->getOptions()->setTtl(0);

        return $apcuCache;
    }

    /**
     * @throws Exception\RuntimeException
     */
    public function getMemcachedService(Container $container): StorageInterface
    {
        $memcachedCache = $this->getCacheAdapter($container, 'memcached');
        $memcachedCache->getOptions()->setTtl(0);

        return $memcachedCache;
    }

    /**
     * This will get the storage adapter that is configured in the plugin parameters
     *
     * @throws Exception\RuntimeException
     */
    public function getStorageInterfaceService(Container $container): StorageInterface
    {
        $params = $container->get(Registry::class);

        $cache = $this->getCacheAdapter(
            $container,
            $params->get('pro_cache_storage_adapter', 'filesystem')
        );

        $ttl = max(
            (int)$params->get('cache_lifetime', '900'),
            (int)$params->get('page_cache_lifetime', '900')
        );
        $cache->getOptions()
            ->setNamespace($container->get(CacheInterface::class)->getGlobalCacheNamespace())
            ->setTtl($ttl);
        if ($cache instanceof PluginAwareInterface) {
            $plugin = new ClearExpiredByFactor();
            $plugin->setContainer($container);
            $plugin->getOptions()->setClearingFactor(100);
            $cache->addPlugin($plugin);
        }

        return $cache;
    }

    public function getCallbackCacheService(Container $container): CallbackCache
    {
        return new CallbackCache(
            $container->get(StorageInterface::class),
            new PatternOptions(
                ['cache_output' => false]
            )
        );
    }

    public function getCaptureCacheService(Container $container): CaptureCache
    {
        $publicDir = $container->get(PathsInterface::class)->captureCacheDir();

        if (!file_exists($publicDir)) {
            $html = <<<HTML
<html><head><title></title></head><body></body></html>';
HTML;
            try {
                File::write($publicDir . '/index.html', $html);
            } catch (\Exception $e) {
            }

            $htaccess = <<<APACHECONFIG
<IfModule mod_autoindex.c>
	Options -Indexes
</IfModule>
<IfModule mod_headers.c>
    Header always unset Content-Security-Policy
</IfModule>
APACHECONFIG;

            try {
                File::write($publicDir . '/.htaccess', $htaccess);
            } catch (\Exception $e) {
            }
        }

        return new CaptureCache(
            new PatternOptions(
                [
                    'public_dir' => $publicDir,
                    'file_locking' => true,
                    'file_permission' => 0644,
                    'dir_permission' => 0755,
                    'umask' => false,
                ]
            )
        );
    }

    /**
     * @return StorageInterface&TaggableInterface&IterableInterface
     */
    public function getTaggableInterfaceService(Container $container)
    {
        $cache = $this->getCacheAdapter(
            $container,
            $container->get(Registry::class)->get('pro_cache_storage_adapter', 'filesystem')
        );

        if (!$cache instanceof TaggableInterface || !$cache instanceof IterableInterface) {
            $cache = $this->getCacheAdapter($container, 'filesystem');
        }

        /** @var StorageInterface&TaggableInterface&IterableInterface $cache */
        $cache->getOptions()
            ->setNamespace($container->get(CacheInterface::class)->getTaggableCacheNamespace())
            ->setTtl(0);

        return $cache;
    }

    public function getPageCacheStorageService(Container $container): StorageInterface
    {
        $cache = $this->getCacheAdapter(
            $container,
            $container->get(Registry::class)->get('pro_cache_storage_adapter', 'filesystem')
        );

        $cache->getOptions()
            ->setNamespace($container->get(CacheInterface::class)->getPageCacheNamespace())
            ->setTtl((int)$container->get(Registry::class)->get('page_cache_lifetime', '900'));

        return $cache;
    }
}
