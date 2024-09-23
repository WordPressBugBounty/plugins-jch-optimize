<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads.
 *
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Laminas\Plugins;

use _JchOptimizeVendor\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\Joomla\Filesystem\File;
use _JchOptimizeVendor\Laminas\Cache\Exception\ExceptionInterface;
use _JchOptimizeVendor\Laminas\Cache\Storage\IterableInterface;
use _JchOptimizeVendor\Laminas\Cache\Storage\OptimizableInterface;
use _JchOptimizeVendor\Laminas\Cache\Storage\Plugin\AbstractPlugin;
use _JchOptimizeVendor\Laminas\Cache\Storage\PostEvent;
use _JchOptimizeVendor\Laminas\Cache\Storage\StorageInterface;
use _JchOptimizeVendor\Laminas\Cache\Storage\TaggableInterface;
use _JchOptimizeVendor\Laminas\EventManager\EventManagerInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareTrait;
use JchOptimize\Core\Container\ContainerAwareTrait;
use JchOptimize\Core\PageCache\PageCache;
use JchOptimize\Core\Registry;
use JchOptimize\Core\Uri\Utils;
use JchOptimize\Platform\Cache;
use JchOptimize\Platform\Paths;
use JchOptimize\Platform\Profiler;
use Throwable;

use function time;

\defined('_JCH_EXEC') or exit('Restricted access');
class ClearExpiredByFactor extends AbstractPlugin implements ContainerAwareInterface, LoggerAwareInterface
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;
    public const FLAG = '__CLEAR_EXPIRED_BY_FACTOR_RUNNING__';

    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $callback = [$this, 'clearExpiredByFactor'];
        $this->listeners[] = $events->attach('setItem.post', $callback, $priority);
        $this->listeners[] = $events->attach('setItems.post', $callback, $priority);
    }

    /**
     * @throws \Exception
     */
    public function clearExpiredByFactor(PostEvent $event): void
    {
        $factor = $this->getOptions()->getClearingFactor();
        if ($factor && 1 === \random_int(1, $factor)) {
            $this->clearExpired();
        }
    }

    public static function getFlagId(): string
    {
        return \md5(self::FLAG);
    }

    private function clearExpired()
    {
        !\JCH_DEBUG ?: Profiler::start('ClearExpired');

        /** @var Registry $params */
        $params = $this->container->get('params');

        /** @var IterableInterface&OptimizableInterface&StorageInterface&TaggableInterface $taggableCache */
        $taggableCache = $this->container->get(TaggableInterface::class);
        $cache = $this->container->get(StorageInterface::class);
        $pageCache = $this->container->get(PageCache::class);
        $pageCacheStorage = $pageCache->getStorage();
        $pageCacheStorageOptions = $pageCacheStorage->getOptions();
        $ttlPageCache = $pageCacheStorageOptions->getTtl();
        // This flag must expire after 3 minutes if not deleted
        $pageCacheStorageOptions->setTtl(180);

        try {
            // If plugin already running in another instance, abort
            if ($pageCacheStorage->hasItem(self::getFlagId())) {
                $pageCacheStorageOptions->setTtl($ttlPageCache);

                return;
            }
            // else set flag to disable page caching while running to prevent
            // errors with race conditions
            $pageCacheStorage->setItem(self::getFlagId(), self::FLAG);
        } catch (ExceptionInterface $e) {
            // just return if this didn't work. We'll try again next time
            return;
        }
        // reset TTL
        $pageCacheStorageOptions->setTtl($ttlPageCache);
        $ttl = $cache->getOptions()->getTtl();
        $time = \time();
        $itemDeletedFlag = \false;
        foreach ($taggableCache->getIterator() as $item) {
            $metaData = $taggableCache->getMetadata($item);
            if (!\is_array($metaData) || empty($metaData)) {
                continue;
            }
            $tags = $taggableCache->getTags($item);
            if (!\is_array($tags) || empty($tags)) {
                continue;
            }
            if ('pagecache' == $tags[0]) {
                continue;
            }
            $mtime = (int) $metaData['mtime'];
            if ($time > $mtime + $ttl) {
                foreach ($tags as $pageCacheUrl) {
                    $pageCacheId = $pageCache->getPageCacheId(Utils::uriFor($pageCacheUrl));
                    if (!$pageCache->deleteItemById($pageCacheId)) {
                        continue 2;
                    }
                }
                $cache->removeItem($item);
                $deleteTag = !$cache->hasItem($item);
                if ($deleteTag) {
                    $itemDeletedFlag = \true;
                }
                // We need to also delete the static css/js file if that option is set
                if ('2' == $params->get('htaccess', '2')) {
                    $files = [Paths::cachePath(\false).'/css/'.$item.'.css', Paths::cachePath(\false).'/js/'.$item.'.js'];

                    try {
                        foreach ($files as $file) {
                            if (\file_exists($file)) {
                                File::delete($file);
                                // If for some reason the file still exists don't delete tags
                                if (\file_exists($file)) {
                                    $deleteTag = \false;
                                }

                                break;
                            }
                        }
                    } catch (Throwable) {
                        // Don't bother to delete the tags if this didn't work
                        $deleteTag = \false;
                    }
                }
                if ($deleteTag) {
                    $taggableCache->removeItem($item);
                }
            }
        }
        if ($itemDeletedFlag) {
            // Finally attempt to clean any third party page cache
            Cache::cleanThirdPartyPageCache();
        }
        !\JCH_DEBUG ?: Profiler::stop('ClearExpired', \true);
        // remove flag
        $pageCacheStorage->removeItem(self::getFlagId());
        if ($cache instanceof OptimizableInterface) {
            $cache->optimize();
        }
        $taggableCache->optimize();
    }
}
