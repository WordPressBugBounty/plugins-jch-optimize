<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\WordPress\CrawlObservers;

use _JchOptimizeVendor\V91\GuzzleHttp\Exception\RequestException;
use _JchOptimizeVendor\V91\Psr\Http\Message\ResponseInterface;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareTrait;
use _JchOptimizeVendor\V91\Spatie\Crawler\CrawlObservers\CrawlObserver;

class ReCacheObserver extends CrawlObserver implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct()
    {
    }

    public function crawled(UriInterface $url, ResponseInterface $response, ?UriInterface $foundOnUrl = null): void
    {
        $this->logger?->info(sprintf('Crawled: %s', (string)$url));
    }

    public function crawlFailed(
        UriInterface $url,
        RequestException $requestException,
        ?UriInterface $foundOnUrl = null
    ): void {
        $this->logger?->error(sprintf('Failed: %s (%s)', (string)$url, (string)$requestException->getMessage()));
    }
}
