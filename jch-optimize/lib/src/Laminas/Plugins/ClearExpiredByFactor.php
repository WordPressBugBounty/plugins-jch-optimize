<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2025 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Laminas\Plugins;

use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareTrait;
use _JchOptimizeVendor\V91\Joomla\Filesystem\File;
use _JchOptimizeVendor\V91\Laminas\Cache\Exception\ExceptionInterface;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\IterableInterface;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\OptimizableInterface;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\Plugin\ClearExpiredByFactor as LaminasPluginClearExpiredByFactor;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\PostEvent;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\StorageInterface;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\TaggableInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareTrait;
use Exception;
use JchOptimize\Core\PageCache\PageCache;
use JchOptimize\Core\Platform\CacheInterface;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Platform\ProfilerInterface;
use JchOptimize\Core\Registry;
use JchOptimize\Core\Uri\Utils;
use Throwable;

use function defined;
use function file_exists;
use function is_array;
use function random_int;
use function time;

use const JCH_DEBUG;

defined('_JCH_EXEC') or die('Restricted access');

class ClearExpiredByFactor extends LaminasPluginClearExpiredByFactor implements
    LoggerAwareInterface,
    ContainerAwareInterface
{
    use LoggerAwareTrait;
    use ContainerAwareTrait;

    public const FLAG = '__CLEAR_EXPIRED_BY_FACTOR_RUNNING__';

    /**
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function clearExpiredByFactor(PostEvent $event): void
    {
        $factor = $this->getOptions()->getClearingFactor();
        $params = $this->getContainer()->get(Registry::class);

        if ($params->get('delete_expiry', '1') && $factor && random_int(1, $factor) === 1) {
            $this->clearExpired();
        }
    }

    public static function getFlagId(): string
    {
        return md5(self::FLAG);
    }

    /**
     * @throws ExceptionInterface
     */
    private function clearExpired(): void
    {
        /** @var ProfilerInterface $profiler */
        $profiler = $this->getContainer()->get(ProfilerInterface::class);
        !JCH_DEBUG ?: $profiler->start('ClearExpired');

        /** @var PageCache $pageCache */
        $pageCache = $this->getContainer()->get(PageCache::class);

        try {
            //If plugin already running in another instance, abort
            if ($pageCache->getStorage()->hasItem(self::getFlagId())) {
                return;
            } else {
                //else set flag to disable page caching while running to prevent
                //errors with race conditions
                $pageCache->getStorage()->setItem(self::getFlagId(), self::FLAG);
            }
        } catch (ExceptionInterface) {
            //just return if this didn't work. We'll try again next time
            return;
        }

        /** @var StorageInterface $storage */
        $storage = $this->getContainer()->get(StorageInterface::class);
        $ttl = $storage->getOptions()->getTtl();
        $pageCacheTtl = $pageCache->getStorage()->getOptions()->getTtl();
        $time = time();
        $itemDeletedFlag = false;

        /** @var TaggableInterface&StorageInterface&IterableInterface&OptimizableInterface $taggableCache */
        $taggableCache = $this->getContainer()->get(TaggableInterface::class);
        /** @var Registry $params */
        $params = $this->getContainer()->get(Registry::class);
        /** @var PathsInterface $paths */
        $paths = $this->getContainer()->get(PathsInterface::class);
        foreach ($taggableCache as $item) {
            $metaData = $taggableCache->getMetadata($item);

            if (!is_array($metaData) || empty($metaData)) {
                continue;
            }

            $tags = $taggableCache->getTags($item);

            if (!is_array($tags) || empty($tags)) {
                continue;
            }

            $mtime = (int)$metaData['mtime'];

            if ($tags[0] == 'pagecache') {
                if ($mtime && $time > $mtime + $pageCacheTtl) {
                    $pageCache->deleteItemById($item);
                }

                continue;
            }

            if ($mtime && $time > $mtime + $ttl) {
                $allRelatedPageCachesSuccessfullyProcessed = true; // Initialize flag

                foreach ($tags as $pageCacheUrl) {
                    $pageCacheId = $pageCache->getPageCacheId(Utils::uriFor($pageCacheUrl));

                    // Check if page cache exists before attempting deletion
                    if (
                        $pageCache->getStorage()->hasItem($pageCacheId)
                        || $pageCache->hasCaptureCache(Utils::uriFor($pageCacheUrl))
                    ) {
                        if (!$pageCache->deleteItemById($pageCacheId)) {
                            $allRelatedPageCachesSuccessfullyProcessed = false;
                            $this->logger?->warning(
                                "ClearExpiredByFactor: Failed to delete page cache '$pageCacheUrl'
                                     when processing asset '$item'. Asset will not be deleted in this run."
                            );
                            break; // Break from this inner loop (iterating tags)
                        }
                    }
                }

                if ($allRelatedPageCachesSuccessfullyProcessed) {
                    $storage->removeItem($item);
                    $deleteTag = !$storage->hasItem($item);

                    // We need to also delete the static css/js file if that option is set
                    // Ensure $deleteTag is true before attempting to delete files
                    if ($deleteTag && $params->get('htaccess', '2') == '2') {
                        $files = [
                            $paths->cachePath(false) . '/css/' . $item . '.css',
                            $paths->cachePath(false) . '/js/' . $item . '.js'
                        ];

                        try {
                            foreach ($files as $file) {
                                if (file_exists($file)) {
                                    File::delete($file);

                                    //If for some reason the file still exists don't delete tags
                                    if (file_exists($file)) {
                                        $deleteTag = false;
                                    }
                                    break; //Assuming we only need to delete one type (css or js) or the first one found
                                }
                            }
                        } catch (Throwable) {
                            //Don't bother to delete the tags if this didn't work
                            $deleteTag = false;
                        }
                    }

                    if ($deleteTag) {
                        $taggableCache->removeItem($item);
                        $itemDeletedFlag = true; // Set if item and its related files are successfully deleted
                    }
                }
            }
        }

        if ($itemDeletedFlag) {
            //Finally attempt to clean any third party page cache
            $this->getContainer()->get(CacheInterface::class)->cleanThirdPartyPageCache();
        }

        !JCH_DEBUG ?: $profiler->stop('ClearExpired', true);

        //remove flag
        $pageCache->getStorage()->removeItem(self::getFlagId());

        if ($storage instanceof OptimizableInterface) {
            $storage->optimize();
        }

        $s = $pageCache->getStorage();
        if ($s instanceof OptimizableInterface) {
            $s->optimize();
        }

        $taggableCache->optimize();
    }
}
