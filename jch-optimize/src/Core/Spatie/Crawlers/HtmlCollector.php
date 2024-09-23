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

namespace JchOptimize\Core\Spatie\Crawlers;

use Exception;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use JchOptimize\Core\Admin\Json;
use JchOptimize\Core\Helper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Spatie\Crawler\CrawlObservers\CrawlObserver;

class HtmlCollector extends CrawlObserver implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var list<array{url:string, html:string}>
     */
    private array $htmls = [];

    /**
     * @var list<Json>
     */
    private array $messages = [];

    private int $numUrls = 0;

    /**
     * @return void
     */
    public function crawled(UriInterface $url, ResponseInterface $response, ?UriInterface $foundOnUrl = null)
    {
        $body = $response->getBody();
        $body->rewind();
        $html = $body->getContents();

        if (Helper::validateHtml($html)) {
            $this->htmls[] = [
                'url' => (string)$url,
                'html' => $html
            ];
        }

        $originalUrl = Uri::withoutQueryValue($url, 'jchnooptimize');
        $message = 'Crawled URL: ' . $originalUrl;
        $this->logger->info($message);

        $this->messages[] = new Json(null, $message);
        $this->numUrls++;
    }

    /**
     * @return void
     */
    public function crawlFailed(UriInterface $url, RequestException $requestException, ?UriInterface $foundOnUrl = null)
    {
        $message = 'Failed crawling url: ' . Uri::withoutQueryValue(
            $url,
            'jchnooptimize'
        ) . ' with message ' . $requestException->getMessage();
        $this->logger->error($message);

        $this->messages[] = new Json(new Exception($message));
    }

    /**
     * @return void
     */
    public function finishedCrawling()
    {
        $this->messages[] = new Json(null, 'Finished crawling ' . $this->numUrls . ' URLs');
    }

    /**
     * @return list<array{url:string, html:string}>
     */
    public function getHtmls(): array
    {
        return $this->htmls;
    }

    /**
     * @return list<Json>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
