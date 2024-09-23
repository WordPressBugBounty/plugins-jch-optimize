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

namespace JchOptimize\Core\Laminas\Plugins;

use Exception;
use JchOptimize\Core\PageCache\PageCache;
use JchOptimize\Platform\Cache;
use JchOptimize\Platform\Paths;
use JchOptimize\Platform\Profiler;
use Joomla\DI\ContainerAwareInterface;
use Joomla\DI\ContainerAwareTrait;
use Joomla\Filesystem\File;
use Joomla\Registry\Registry;
use Laminas\Cache\Exception\ExceptionInterface;
use Laminas\Cache\Storage\IterableInterface;
use Laminas\Cache\Storage\Plugin\AbstractPlugin;
use Laminas\Cache\Storage\PostEvent;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Cache\Storage\TaggableInterface;
use Laminas\EventManager\EventManagerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;

use function array_column;
use function array_merge;
use function array_reduce;
use function array_unique;
use function count;
use function defined;
use function file_exists;
use function in_array;
use function is_array;
use function random_int;
use function time;

use const JCH_DEBUG;

defined('_JCH_EXEC') or die('Restricted access');

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
     * @throws Exception
     */
    public function clearExpiredByFactor(PostEvent $event): void
    {
        $factor = $this->getOptions()->getClearingFactor();

        if ($factor && random_int(1, $factor) === 1) {
            $this->clearExpired();
        }
    }

    public static function getFlagId(): string
    {
        return md5(self::FLAG);
    }

    /**
     * @return void
     * @throws ExceptionInterface
     */
    private function clearExpired()
    {
        !JCH_DEBUG ?: Profiler::start('ClearExpired');

        /** @var Registry $params */
        $params = $this->container->get('params');
        /** @var StorageInterface&TaggableInterface&IterableInterface $taggableCache */
        $taggableCache = $this->container->get(TaggableInterface::class);
        $cache = $this->container->get(StorageInterface::class);
        $pageCache = $this->container->get(PageCache::class);

        $pageCacheStorage = $pageCache->getStorage();
        $pageCacheStorageOptions = $pageCacheStorage->getOptions();
        $ttlPageCache = $pageCacheStorageOptions->getTtl();
        //This flag must expire after 3 minutes if not deleted
        $pageCacheStorageOptions->setTtl(180);

        //If plugin already running in another instance, abort
        if ($pageCacheStorage->hasItem(self::getFlagId())) {
            $pageCacheStorageOptions->setTtl($ttlPageCache);

            return;
        } else {
            //else set flag to disable page caching while running to prevent
            //errors with race conditions
            $pageCacheStorage->setItem(self::getFlagId(), self::FLAG);
        }

        //reset TTL
        $pageCacheStorageOptions->setTtl($ttlPageCache);

        $ttl = $cache->getOptions()->getTtl();
        $time = time();

        //Let's build an array of items to delete
        /** @var array<string, array{page_cache_id?:string[], items_on_page?:string[]}> $itemsToDelete */
        $itemsToDelete = [];
        /** @var array<string, array{mtime:int, id:string}> $pageItems */
        $pageItems = [];

        /** @var string[] $iterator */
        $iterator = $taggableCache->getIterator();
        foreach ($iterator as $item) {
            $tags = $taggableCache->getTags($item);

            if (!is_array($tags) || empty($tags)) {
                continue;
            }

            $metaData = $taggableCache->getMetadata($item);

            if (!is_array($metaData) || empty($metaData)) {
                continue;
            }

            $mtime = (int)$metaData['mtime'];

            //Handle items that are not page cache
            if (isset($tags[0]) && $tags[0] != 'pagecache') {
                //If item was only used on the page once, then delete after page cache expires in conservative mode
                if ((count($tags) === 1
                        && $time >= $mtime + $ttlPageCache)
                    //In aggressive mode we also delete any expired cache
                    || ($params->get('delete_expiry_mode', '0') == '1'
                        && $time >= $mtime + $ttl)) {
                    //Add each tag as index of array and attach cache item
                    foreach ($tags as $tag) {
                        $itemsToDelete[$tag]['items_on_page'][] = $item;
                    }
                }
            }

            //Record each page item for now with their mtime
            if (isset($tags[0]) && $tags[0] == 'pagecache') {
                $pageItems[$tags[1]] = [
                    'mtime' => $mtime,
                    'id' => $item
                ];
            }
        }

        unset($iterator);

        //Collate page cache items
        foreach ($pageItems as $url => $pageItem) {
            if (isset($itemsToDelete[$url]) || $time >= $pageItem['mtime'] + $ttlPageCache) {
                $itemsToDelete[$url]['page_cache_id'][] = $pageItem['id'];
            }
        }

        unset($pageItems);

        //Collect items that were on a page that wasn't deleted successfully
        $dontDeleteItems = [];
        //Delete page cache
        foreach ($itemsToDelete as $url => $itemsStack) {
            //If page cache exists and wasn't successfully deleted, don't delete items on page
            if (isset($itemsStack['page_cache_id'])) {
                foreach ($itemsStack['page_cache_id'] as $pageCacheId) {
                    if (!$pageCache->deleteItemById($pageCacheId) && isset($itemsStack['items_on_page'])) {
                        $dontDeleteItems = array_merge($dontDeleteItems, $itemsStack['items_on_page']);
                        unset($itemsToDelete[$url]);
                    }
                }
            }
        }

        /** @var string[] $itemsOnPages */
        $itemsOnPages = array_unique(
            array_reduce(array_column($itemsToDelete, 'items_on_page'), 'array_merge', [])
        );

        unset($itemsToDelete);

        //Delete items on page
        foreach ($itemsOnPages as $key => $itemOnPage) {
            if (in_array($itemOnPage, $dontDeleteItems)) {
                unset($itemsOnPages[$key]);
                continue;
            }

            $cache->removeItem($itemOnPage);
            $deleteTag = !$cache->hasItem($itemOnPage);
            //We need to also delete the static css/js file if that option is set
            if ($params->get('htaccess', '2') == '2') {
                $files = [
                    Paths::cachePath(false) . '/css/' . $itemOnPage . '.css',
                    Paths::cachePath(false) . '/js/' . $itemOnPage . '.js'
                ];

                try {
                    foreach ($files as $file) {
                        if (file_exists($file)) {
                            File::delete($file);

                            //If for some reason the file still exists don't delete tags
                            if (file_exists($file)) {
                                $deleteTag = false;
                            }

                            break;
                        }
                    }
                } catch (Throwable $e) {
                    //Don't bother to delete the tags if this didn't work
                    $deleteTag = false;
                }
            }

            if ($deleteTag) {
                $taggableCache->removeItem($itemOnPage);
            }
        }

        if (!empty($itemsOnPages)) {
            //Finally attempt to clean any third party page cache
            Cache::cleanThirdPartyPageCache();
        }

        !JCH_DEBUG ?: Profiler::stop('ClearExpired', true);

        //remove flag
        $pageCacheStorage->removeItem(self::getFlagId());
    }
}
