<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Service\Provider;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Joomla\DI\ServiceProviderInterface;
use _JchOptimizeVendor\V91\Laminas\Cache\Service\StorageCacheAbstractServiceFactory;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\ClearByNamespaceInterface;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\IterableInterface;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\StorageInterface;
use _JchOptimizeVendor\V91\Spatie\Crawler\CrawlQueues\CrawlQueue;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Laminas\CacheConfigurationContainerFactory;
use JchOptimize\Core\Registry;
use JchOptimize\Core\Spatie\CrawlQueues\CacheCrawlQueue;
use JchOptimize\Core\Spatie\CrawlQueues\NonOptimizedCacheCrawlQueue;

class Spatie implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->alias(CacheCrawlQueue::class, CrawlQueue::class)
            ->share(CrawlQueue::class, [$this, 'getCacheCrawlQueueProvider']);
        $container->share(NonOptimizedCacheCrawlQueue::class, [$this, 'getNonOptimizedCacheCrawlQueueProvider']);
    }

    public function getCacheCrawlQueueProvider(Container $container): CacheCrawlQueue
    {
        $adapter = $container->get(Registry::class)->get('pro_cache_storage_adapter', 'filesystem');

        return new CacheCrawlQueue(
            $this->getStorage($container, $adapter),
            $this->getStorage($container, $adapter)
        );
    }

    public function getNonOptimizedCacheCrawlQueueProvider(Container $container): NonOptimizedCacheCrawlQueue
    {
        /** @var string $adapter */
        $adapter = $container->get(Registry::class)->get('pro_cache_storage_adapter', 'filesystem');

        return new NonOptimizedCacheCrawlQueue(
            $this->getStorage($container, $adapter),
            $this->getStorage($container, $adapter)
        );
    }

    /**
     * @param Container $container
     * @param string $adapter
     * @return StorageInterface&ClearByNamespaceInterface&IterableInterface
     */
    private function getStorage(Container $container, string $adapter): StorageInterface
    {
        if ($adapter == 'filesystem') {
            Helper::createCacheFolder($container);
        }

        $laminasContainer = CacheConfigurationContainerFactory::create($container);

        $factory = new StorageCacheAbstractServiceFactory();
        /** @var StorageInterface $storage */
        $storage = $factory($laminasContainer, $adapter);

        if (
            !$storage instanceof IterableInterface
            || !$storage instanceof ClearByNamespaceInterface
        ) {
            $storage = $this->getStorage($container, 'filesystem');
        }

        $storage->getOptions()
            ->setTtl(0);

        return $storage;
    }
}
