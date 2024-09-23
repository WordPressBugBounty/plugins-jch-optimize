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

namespace JchOptimize\Core\Service;

use JchOptimize\Core\Helper;
use JchOptimize\Core\Spatie\CrawlQueues\CacheCrawlQueue;
use JchOptimize\Core\Spatie\CrawlQueues\NonOptimizedCacheCrawlQueue;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Registry\Registry;
use Laminas\Cache\Service\StorageCacheAbstractServiceFactory;
use Laminas\Cache\Storage\ClearByNamespaceInterface;
use Laminas\Cache\Storage\IterableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Spatie\Crawler\CrawlQueues\CrawlQueue;

class SpatieProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container->alias(CrawlQueue::class, CacheCrawlQueue::class)
            ->set(CacheCrawlQueue::class, [$this, 'getCacheCrawlQueueProvider'], true);
        $container->set(NonOptimizedCacheCrawlQueue::class, [$this, 'getNonOptimizedCacheCrawlQueueProvider'], true);
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
            Helper::createCacheFolder();
        }

        $factory = new StorageCacheAbstractServiceFactory();
        /** @var StorageInterface $storage */
        $storage = $factory($container, $adapter);

        if (!$storage instanceof IterableInterface
            || !$storage instanceof ClearByNamespaceInterface) {
            $storage = $this->getStorage($container, 'filesystem');
        }

        $storage->getOptions()
            ->setTtl(0);

        return $storage;
    }
}
