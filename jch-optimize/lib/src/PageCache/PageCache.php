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

namespace JchOptimize\Core\PageCache;

use _JchOptimizeVendor\GuzzleHttp\Psr7\Uri;
use _JchOptimizeVendor\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\Joomla\Input\Input;
use _JchOptimizeVendor\Laminas\Cache\Exception\ExceptionInterface;
use _JchOptimizeVendor\Laminas\Cache\Storage\IterableInterface;
use _JchOptimizeVendor\Laminas\Cache\Storage\StorageInterface;
use _JchOptimizeVendor\Laminas\Cache\Storage\TaggableInterface;
use _JchOptimizeVendor\Psr\Http\Message\UriInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareTrait;
use JchOptimize\Core\Container\ContainerAwareTrait;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Laminas\Plugins\ClearExpiredByFactor;
use JchOptimize\Core\Registry;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\UriNormalizer;
use JchOptimize\Platform\Cache;
use JchOptimize\Platform\Hooks;
use JchOptimize\Platform\Utility;

use function time;

\defined('_JCH_EXEC') or exit('Restricted access');
class PageCache implements ContainerAwareInterface, LoggerAwareInterface
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;

    protected Registry $params;

    protected StorageInterface $pageCacheStorage;

    /**
     * Cache id.
     */
    protected string $cacheId = '';

    /**
     * Files system cache adapter used to store tags when another adapter is being used that isn't taggable and iterable.
     *
     * @var IterableInterface&StorageInterface&TaggableInterface
     */
    protected $taggableCache;

    /**
     * Name of currently used cache adapter.
     */
    protected string $adapter;

    /**
     * Indicates whether CaptureCache is used to store cache.
     */
    protected bool $captureCacheEnabled = \false;

    protected array $filters = [];

    protected array $lists = ['list_fullordering' => 'mtime ASC'];

    protected bool $enabled = \true;

    protected bool $isCachingSet = \false;

    protected Input $input;

    /**
     * Constructor.
     *
     * @param IterableInterface&StorageInterface&TaggableInterface $taggableCache
     */
    public function __construct(Registry $params, Input $input, StorageInterface $pageCacheStorage, $taggableCache)
    {
        $this->params = $params;
        $this->input = $input;
        $this->pageCacheStorage = $pageCacheStorage;
        $this->taggableCache = $taggableCache;
        $reflection = new \ReflectionClass($this->pageCacheStorage);
        $this->adapter = $reflection->getShortName();
    }

    public function setFilter(string $key, string $filter): void
    {
        $this->filters[$key] = $filter;
    }

    public function setList(string $key, string $list): void
    {
        $this->lists[$key] = $list;
    }

    /**
     * @return list<array{id:string, url:string, device:string, adapter:string, http-request:string, mtime:int}>
     *
     * @throws ExceptionInterface
     */
    public function getItems(): array
    {
        $items = [];

        /** @var string[] $iterator */
        $iterator = $this->taggableCache->getIterator();
        foreach ($iterator as $cacheItem) {
            $tags = $this->taggableCache->getTags($cacheItem);

            /** @var array{mtime:int} $metaData */
            $metaData = $this->taggableCache->getMetadata($cacheItem);
            if (empty($tags)) {
                continue;
            }
            if ('pagecache' != $tags[0]) {
                continue;
            }
            $url = $tags[1];
            $mtime = $metaData['mtime'];
            // Filter bu Time 1
            if (!empty($this->filters['filter_time-1'])) {
                if (\time() < $mtime + (int) $this->filters['filter_time-1']) {
                    continue;
                }
            }
            // Filter by Time 2
            if (!empty($this->filters['filter_time-2'])) {
                if (\time() >= $mtime + (int) $this->filters['filter_time-2']) {
                    continue;
                }
            }
            // Filter by URL
            if (!empty($this->filters['filter_search'])) {
                if (\false === \strpos($url, $this->filters['filter_search'])) {
                    continue;
                }
            }
            // Filter by device
            if (!empty($this->filters['filter_device'])) {
                if ($tags[2] != $this->filters['filter_device']) {
                    continue;
                }
            }
            // Filter by adapter
            if (!empty($this->filters['filter_adapter'])) {
                if ($tags[3] != $this->filters['filter_adapter']) {
                    continue;
                }
            }
            $item = [];
            $item['id'] = $cacheItem;
            $item['url'] = $tags[1];
            $item['device'] = $tags[2];
            $item['adapter'] = $tags[3];
            $item['http-request'] = 'no';
            $item['mtime'] = $metaData['mtime'];
            $items[] = $item;
        }
        $this->sortItems($items, $this->lists['list_fullordering']);
        if (!empty($this->lists['list_limit'])) {
            $items = \array_slice($items, 0, (int) $this->lists['list_limit']);
        }

        return $items;
    }

    public function store(string $html): string
    {
        if ($this->getCachingEnabled()) {
            $html = $this->tagHtml($html);
            $data = ['body' => $html, 'headers' => Utility::getHeaders()];
            // Save an empty page using the same id then tag it
            $this->taggableCache->setItem($this->cacheId, '<html lang><head><title></title></head><body></body></html>');
            $this->taggableCache->setTags($this->cacheId, $this->getPageCacheTags());
            // If tag successfully saved then save page cache
            if (!empty($this->taggableCache->getTags($this->cacheId))) {
                $this->pageCacheStorage->setItem($this->cacheId, $data);
            }
        } else {
            // Ensure Capture cache  doesn't cache either
            $this->captureCacheEnabled = \false;
        }

        return $html;
    }

    /**
     * Returns the caching status if enabled or disabled.` If caching wasn't explicitly set it will be set on
     * first call to this function.
     *
     * @throws ExceptionInterface
     */
    public function getCachingEnabled(): bool
    {
        if (!$this->isCachingSet) {
            $this->setCaching();
        }
        // Disable page caching anytime clear expired plugin is running.
        return $this->enabled && !$this->pageCacheStorage->hasItem(ClearExpiredByFactor::getFlagId());
    }

    public function setCaching(): void
    {
        // just return false with this filter if you don't want the page to be cached
        if (!Hooks::onPageCacheSetCaching()) {
            $this->disableCaching();

            return;
        }
        if ('POST' == $this->input->server->get('REQUEST_METHOD') || 'user_posted_form' == $this->input->cookie->get('jch_optimize_no_cache_user_activity')) {
            $this->disableCaching();

            return;
        }
        $this->enabled = $this->params->get('page_cache_select', 'jchoptimizepagecache') && Cache::isPageCacheEnabled($this->params) && Utility::isGuest() && !self::isExcluded($this->params) && 'GET' === $this->input->server->get('REQUEST_METHOD');
        $this->isCachingSet = \true;
    }

    public function disableCaching(): void
    {
        $this->enabled = \false;
        $this->isCachingSet = \true;
    }

    public function getCurrentPage(): UriInterface
    {
        $pageUri = SystemUri::currentUri();

        /** @var string[] $ignoredQueries */
        $ignoredQueries = $this->params->get('page_cache_ignore_query_values', []);
        foreach ($ignoredQueries as $queryValue) {
            $pageUri = Uri::withoutQueryValue($pageUri, $queryValue);
        }

        return $pageUri;
    }

    public function tagHtml(string $html)
    {
        if (JCH_DEBUG) {
            $now = \date('l, F d, Y h:i:s A');
            $tag = "\n".'<!-- Cached by JCH Optimize on '.$now.' GMT -->'."\n".'</body>';
            $html = \str_replace('</body>', $tag, $html);
        }

        return $html;
    }

    /**
     * @throws \Exception
     */
    public function deleteCurrentPage(): void
    {
        $this->deleteItemsByUrls([$this->getCurrentPage()]);
    }

    /**
     * @throws \Exception
     */
    public function deleteItemsByUrls(array $urls): void
    {
        $iterator = $this->taggableCache->getIterator();
        foreach ($iterator as $item) {
            $tags = $this->taggableCache->getTags($item);
            if (isset($tags[0]) && 'pagecache' == $tags[0] && \in_array($tags[1], $urls)) {
                $this->deleteItemById($item);
            }
        }
    }

    public function deleteItemsByIds(array $ids): bool
    {
        $result = 1;
        foreach ($ids as $id) {
            $result &= (int) $this->deleteItemById($id);
        }

        return (bool) $result;
    }

    public function deleteItemById(string $id): bool
    {
        $result = 1;
        $tags = $this->taggableCache->getTags($id);
        if (!empty($tags) && $tags[3] != $this->adapter) {
            $this->container->get($tags[3])->removeItem($id);
            $result &= (int) (!$this->container->get($tags[3])->hasItem($id));
        } else {
            $this->pageCacheStorage->removeItem($id);
            $result &= (int) (!$this->pageCacheStorage->hasItem($id));
        }
        // Only delete tag if successful
        if ($result) {
            $this->taggableCache->removeItem($id);
        }

        return (bool) $result;
    }

    public function removeHtmlTag(string $html): ?string
    {
        $search = '#<!-- Cached by JCH Optimize on .*? GMT -->\\n#';

        return \preg_replace($search, '', $html);
    }

    /**
     * @throws ExceptionInterface
     */
    public function initialize(): void
    {
        $this->setCaching();
        $this->cacheId = $this->getPageCacheId();
        if ('POST' == $this->input->server->get('REQUEST_METHOD')) {
            if ($this->params->get('page_cache_exclude_form_users', '1')) {
                Hooks::onUserPostForm();
                if ('user_posted_form' == !$this->input->cookie->get('jch_optimize_no_cache_user_activity')) {
                    $options = ['httponly' => \true, 'expires' => \time() + (int) $this->params->get('page_cache_lifetime', '900')];
                    $this->input->cookie->set('jch_optimize_no_cache_user_activity', 'user_posted_form', $options);
                }
            }

            return;
        }
        if (!$this->params->get('page_cache_exclude_form_users', '0') && 'user_posted_form' == $this->input->cookie->get('jch_optimize_no_cache_user_activity')) {
            Hooks::onUserPostFormDeleteCookie();
            $this->input->cookie->set('jch_optimize_no_cache_user_activity', '', ['expires' => 1]);
        }
        if (!$this->enabled) {
            return;
        }

        /** @var null|array $data */
        $data = $this->pageCacheStorage->getItem($this->cacheId);
        $data = Cache::prepareDataFromCache($data);
        if (!\is_null($data) && 'user_posted_form' != $this->input->cookie->get('jch_optimize_no_cache_user_activity')) {
            if (!empty($data['body'])) {
                $this->setCaptureCache($data['body']);
            }
            while (@\ob_end_clean());

            Cache::outputData($data);
        }
    }

    public function getPageCacheId(?UriInterface $currentUri = null): string
    {
        if (null === $currentUri) {
            $currentUri = $this->getCurrentPage();
        }
        // Add a value to the array that will be used to determine the page cache id
        $parts = Hooks::onPageCacheGetKey([]);
        $parts[] = $this->adapter;
        $parts[] = (string) UriNormalizer::pageCacheIdNormalize($currentUri);
        $parts[] = \serialize($this->params);
        if (JCH_PRO === '1' && $this->params->get('pro_cache_platform', '0') && Utility::isMobile()) {
            $parts[] = '__MOBILE__';
        }

        return \md5(\serialize($parts));
    }

    public function getAdapterName(): string
    {
        return $this->adapter;
    }

    public function deleteAllItems(): bool
    {
        $return = 1;

        /** @var string[] $iterator */
        $iterator = $this->taggableCache->getIterator();
        foreach ($iterator as $item) {
            $tags = $this->taggableCache->getTags($item);
            if (!empty($tags) && 'pagecache' == $tags[0]) {
                $return &= (int) $this->deleteItemById($item);
            }
        }

        return (bool) $return;
    }

    public function isCaptureCacheEnabled(): bool
    {
        return $this->captureCacheEnabled;
    }

    public function disableCaptureCache(): void
    {
        $this->captureCacheEnabled = \false;
    }

    public function getStorage(): StorageInterface
    {
        return $this->pageCacheStorage;
    }

    public function hasPageCache(UriInterface $uri): bool
    {
        $id = $this->getPageCacheId($uri);

        return $this->pageCacheStorage->hasItem($id);
    }

    public function hasCaptureCache(UriInterface $uri): bool
    {
        return \false;
    }

    /**
     * @param list<array{id:string, url:string, device:string, adapter:string, http-request:string, mtime:int}> $items
     */
    protected function sortItems(array &$items, string $fullOrdering): void
    {
        [$orderBy, $dir] = \explode(' ', $fullOrdering);
        \usort($items, function ($a, $b) use ($orderBy, $dir) {
            if ('ASC' == $dir) {
                return $a[$orderBy] <=> $b[$orderBy];
            }

            return $b[$orderBy] <=> $a[$orderBy];
        });
    }

    protected function isExcluded(Registry $params): bool
    {
        $cache_exclude = $params->get('cache_exclude', []);
        if (Helper::findExcludes($cache_exclude, (string) $this->getCurrentPage())) {
            return \true;
        }

        return \false;
    }

    protected function getPageCacheTags(): array
    {
        $device = Utility::isMobile() ? 'Mobile' : 'Desktop';

        return ['pagecache', $this->getCurrentPage(), $device, $this->adapter];
    }

    /**
     * To be overwritten by the CaptureCache class.
     */
    protected function setCaptureCache(string $html)
    {
    }
}
