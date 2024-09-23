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

namespace JchOptimize\Core\Spatie\CrawlQueues;

use Exception;
use Laminas\Cache\Exception\ExceptionInterface;
use Laminas\Cache\Storage\ClearByNamespaceInterface;
use Laminas\Cache\Storage\IterableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlQueues\CrawlQueue;
use Spatie\Crawler\CrawlUrl;
use Spatie\Crawler\Exceptions\InvalidUrl;
use Spatie\Crawler\Exceptions\UrlNotFoundByIndex;

use function iterator_count;
use function md5;

class CacheCrawlQueue implements CrawlQueue
{
    /**
     * @var StorageInterface&ClearByNamespaceInterface&IterableInterface
     */
    protected StorageInterface $storage;

    /**
     * @var StorageInterface&ClearByNamespaceInterface&IterableInterface
     */
    protected StorageInterface $pendingStorage;

    protected const URLS_NAMESPACE = 'jchoptimizeurls';

    protected const PENDING_URLS_NAMESPACE = 'jchoptimizependingurls';

    /**
     * @param StorageInterface&ClearByNamespaceInterface&IterableInterface $storage
     * @param StorageInterface&ClearByNamespaceInterface&IterableInterface $pendingStorage
     */
    public function __construct(StorageInterface $storage, StorageInterface $pendingStorage)
    {
        $this->storage = $storage;
        $this->storage->getOptions()
            ->setNamespace(self::URLS_NAMESPACE);

        $this->pendingStorage = $pendingStorage;
        $this->pendingStorage->getOptions()
            ->setNamespace(self::PENDING_URLS_NAMESPACE);
    }

    /**
     * @throws ExceptionInterface
     * @throws InvalidUrl
     */
    public function add(CrawlUrl $url): CrawlQueue
    {
        $urlId = $this->getUrlId($url);
        $url->setId($urlId);

        $this->storage->addItem($urlId, $url);
        $this->pendingStorage->addItem($urlId, $url);

        return $this;
    }

    /**
     * @param CrawlUrl|UriInterface $crawlUrl
     * @throws ExceptionInterface
     * @throws InvalidUrl
     */
    public function has($crawlUrl): bool
    {
        if ($crawlUrl instanceof CrawlUrl || $crawlUrl instanceof UriInterface) {
            return $this->storage->hasItem($this->getUrlId($crawlUrl));
        }

        throw InvalidUrl::unexpectedType($crawlUrl);
    }

    /**
     * @throws Exception
     */
    public function hasPendingUrls(): bool
    {
        return (bool)iterator_count($this->pendingStorage->getIterator());
    }

    /**
     * @param string $id
     * @return CrawlUrl
     * @throws ExceptionInterface
     */
    public function getUrlById($id): CrawlUrl
    {
        $success = null;
        /** @var CrawlUrl $result */
        $result = $this->storage->getItem($id, $success);

        if (!$success) {
            throw new UrlNotFoundByIndex("Crawl url with id {$id} not found in cache");
        }

        return $result;
    }

    /**
     * @throws ExceptionInterface
     * @throws Exception
     */
    public function getPendingUrl(): ?CrawlUrl
    {
        /** @var string[] $iterator */
        $iterator = $this->pendingStorage->getIterator();
        foreach ($iterator as $item) {
            /** @var CrawlUrl $uri */
            $uri = $this->pendingStorage->getItem($item);

            return $uri;
        }

        return null;
    }

    /**
     * @throws ExceptionInterface
     * @throws InvalidUrl
     */
    public function hasAlreadyBeenProcessed(CrawlUrl $url): bool
    {
        $id = $this->getUrlId($url);

        if ($this->pendingStorage->hasItem($id)) {
            return false;
        }

        if ($this->storage->hasItem($id)) {
            return true;
        }

        return false;
    }

    /**
     * @throws ExceptionInterface
     * @throws InvalidUrl
     */
    public function markAsProcessed(CrawlUrl $crawlUrl): void
    {
        $id = $this->getUrlId($crawlUrl);

        $this->pendingStorage->removeItem($id);
    }

    /**
     * @throws Exception
     */
    public function getProcessedUrlCount(): int
    {
        return iterator_count($this->storage->getIterator()) - iterator_count($this->pendingStorage->getIterator());
    }

    /**
     * @param CrawlUrl|UriInterface $crawlUrl
     *
     * @throws InvalidUrl
     */
    protected function getUrlId($crawlUrl): string
    {
        if ($crawlUrl instanceof CrawlUrl) {
            return md5((string)$crawlUrl->url);
        }

        if ($crawlUrl instanceof UriInterface) {
            return md5((string)$crawlUrl);
        }

        throw InvalidUrl::unexpectedType($crawlUrl);
    }

    public function __destruct()
    {
        $this->storage->clearByNameSpace(self::URLS_NAMESPACE);
        $this->pendingStorage->clearByNameSpace(self::PENDING_URLS_NAMESPACE);
    }
}
