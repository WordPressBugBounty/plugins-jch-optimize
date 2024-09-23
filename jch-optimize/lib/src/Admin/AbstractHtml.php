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

namespace JchOptimize\Core\Admin;

use _JchOptimizeVendor\GuzzleHttp\Client;
use _JchOptimizeVendor\GuzzleHttp\Psr7\Uri;
use _JchOptimizeVendor\GuzzleHttp\RequestOptions;
use _JchOptimizeVendor\Joomla\DI\Container;
use _JchOptimizeVendor\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\Psr\Http\Client\ClientInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareTrait;
use _JchOptimizeVendor\Psr\Log\NullLogger;
use _JchOptimizeVendor\Spatie\Crawler\Crawler;
use _JchOptimizeVendor\Spatie\Crawler\CrawlProfiles\CrawlInternalUrls;
use JchOptimize\Core\Admin\API\MessageEventInterface;
use JchOptimize\Core\Container\ContainerAwareTrait;
use JchOptimize\Core\Interfaces\Html;
use JchOptimize\Core\Registry;
use JchOptimize\Core\Spatie\Crawlers\HtmlCollector;
use JchOptimize\Core\Spatie\CrawlQueues\NonOptimizedCacheCrawlQueue;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\UriComparator;

\defined('_JCH_EXEC') or exit('Restricted access');
abstract class AbstractHtml implements Html, LoggerAwareInterface, ContainerAwareInterface
{
    use LoggerAwareTrait;
    use ContainerAwareTrait;

    /**
     * JCH Optimize settings.
     */
    protected Registry $params;

    /**
     * Http client transporter object.
     *
     * @var Client&ClientInterface
     */
    protected $http;
    private HtmlCollector $htmlCollector;

    /**
     * @param Client&ClientInterface $http
     */
    public function __construct(Registry $params, Container $container, $http)
    {
        $this->params = $params;
        $this->container = $container;
        $this->http = $http;
        $this->htmlCollector = new HtmlCollector();
    }

    /**
     * @param array{base_url?:string, crawl_limit?:int} $options
     *
     * @return list<array{url:string, html:string}>
     *
     * @throws \Exception
     */
    public function getCrawledHtmls(array $options = []): array
    {
        $defaultOptions = ['crawl_limit' => 10, 'base_url' => SystemUri::currentBaseFull()];
        $options = \array_merge($defaultOptions, $options);
        if (UriComparator::isCrossOrigin(new Uri($options['base_url']))) {
            throw new \Exception('Cross origin URLs not allowed');
        }
        $clientOptions = [RequestOptions::COOKIES => \false, RequestOptions::CONNECT_TIMEOUT => 10, RequestOptions::TIMEOUT => 10, RequestOptions::ALLOW_REDIRECTS => \true, RequestOptions::HEADERS => ['User-Agent' => $_SERVER['HTTP_USER_AGENT'] ?? '*']];
        Crawler::create($clientOptions)->setCrawlObserver($this->htmlCollector)->setParseableMimeTypes(['text/html'])->ignoreRobots()->setTotalCrawlLimit($options['crawl_limit'])->setCrawlQueue($this->container->get(NonOptimizedCacheCrawlQueue::class))->setCrawlProfile(new CrawlInternalUrls($options['base_url']))->startCrawling($options['base_url']);

        return $this->htmlCollector->getHtmls();
    }

    public function setEventLogging(bool $logging = \true, ?MessageEventInterface $messageEventObj = null): void
    {
        if ($logging && null !== $this->logger) {
            $this->htmlCollector->setEventLogging(\true);
            $this->htmlCollector->setLogger($this->logger);
        } else {
            $this->htmlCollector->setLogger(new NullLogger());
        }
        $this->htmlCollector->setMessageEventObj($messageEventObj);
    }
}
