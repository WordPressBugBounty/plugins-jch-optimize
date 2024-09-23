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

namespace JchOptimize\Core\Admin;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use JchOptimize\Core\Interfaces\Html;
use JchOptimize\Core\Spatie\Crawlers\HtmlCollector;
use JchOptimize\Core\Spatie\CrawlQueues\NonOptimizedCacheCrawlQueue;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\UriComparator;
use Joomla\DI\ContainerAwareInterface;
use Joomla\DI\ContainerAwareTrait;
use Joomla\Registry\Registry;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlProfiles\CrawlInternalUrls;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

abstract class AbstractHtml implements Html, LoggerAwareInterface, ContainerAwareInterface
{
    use LoggerAwareTrait;
    use ContainerAwareTrait;

    /**
     * JCH Optimize settings
     */
    protected Registry $params;

    /**
     * Http client transporter object
     * @var Client&ClientInterface
     */
    protected $http;

    private bool $logging = false;

    /**
     * @param Registry $params
     * @param Client&ClientInterface $http
     */
    public function __construct(Registry $params, $http)
    {
        $this->params = $params;
        $this->http = $http;
    }

    /**
     * @param array{base_url?:string, crawl_limit?:int} $options
     * @return array{list<array{url:string, html:string}>, list<Json>}
     * @throws Exception
     */
    public function getCrawledHtmls(array $options = []): array
    {
        $defaultOptions = [
            'crawl_limit' => 10,
            'base_url' => SystemUri::currentBaseFull()
        ];

        $options = array_merge($defaultOptions, $options);

        if (UriComparator::isCrossOrigin(new Uri($options['base_url']))) {
            throw new Exception('Cross origin URLs not allowed');
        }

        $htmlCollector = new HtmlCollector();
        $this->logger = $this->logger ?? new NullLogger();
        $logger = $this->logging ? $this->logger : new NullLogger();
        $htmlCollector->setLogger($logger);

        $clientOptions = [
            RequestOptions::COOKIES => false,
            RequestOptions::CONNECT_TIMEOUT => 10,
            RequestOptions::TIMEOUT => 10,
            RequestOptions::ALLOW_REDIRECTS => true,
            RequestOptions::HEADERS => [
                'User-Agent' => $_SERVER['HTTP_USER_AGENT'] ?? '*'
            ]
        ];

        Crawler::create($clientOptions)
            ->setCrawlObserver($htmlCollector)
            ->setParseableMimeTypes(['text/html'])
            ->ignoreRobots()
            ->setTotalCrawlLimit($options['crawl_limit'])
            ->setCrawlQueue($this->container->get(NonOptimizedCacheCrawlQueue::class))
            ->setCrawlProfile(new CrawlInternalUrls($options['base_url']))
            ->startCrawling($options['base_url']);

        return [$htmlCollector->getHtmls(), $htmlCollector->getMessages()];
    }

    public function setLogging(bool $state = true): void
    {
        $this->logging = $state;
    }
}
