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

namespace JchOptimize\Core;

// No direct access
use _JchOptimizeVendor\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareTrait;
use CodeAlfa\Minify\Html;
use JchOptimize\Core\Container\ContainerAwareTrait;
use JchOptimize\Core\FeatureHelpers\ReduceDom;
use JchOptimize\Core\Html\CacheManager;
use JchOptimize\Core\Html\HtmlManager;
use JchOptimize\Core\Html\Processor as HtmlProcessor;
use JchOptimize\Platform\Profiler;
use JchOptimize\Platform\Utility;

\defined('_JCH_EXEC') or exit('Restricted access');

/**
 * Main plugin file.
 */
class Optimize implements LoggerAwareInterface, ContainerAwareInterface
{
    use LoggerAwareTrait;
    use ContainerAwareTrait;

    /**
     * @var Registry
     */
    private \JchOptimize\Core\Registry $params;

    private HtmlProcessor $htmlProcessor;

    private CacheManager $cacheManager;

    private HtmlManager $htmlManager;

    private string $html = '';

    private string $jit = '1';

    /**
     * @var Http2Preload
     *
     * @since version
     */
    private \JchOptimize\Core\Http2Preload $http2Preload;

    /**
     * Constructor.
     *
     * @throws Exception\RuntimeException
     */
    public function __construct(Registry $params, HtmlProcessor $htmlProcessor, CacheManager $cacheManager, HtmlManager $htmlManager, Http2Preload $http2Preload)
    {
        \ini_set('pcre.backtrack_limit', '1000000');
        \ini_set('pcre.recursion_limit', '1000000');
        if (\version_compare(\PHP_VERSION, '7.0.0', '>=')) {
            $this->jit = \ini_get('pcre.jit');
            \ini_set('pcre.jit', '0');
        }
        if (\version_compare(\PHP_VERSION, '7.3', '<')) {
            throw new \JchOptimize\Core\Exception\RuntimeException('PHP Version less than 7.3, Exiting plugin...');
        }
        $pcre_version = \preg_replace('#(^\\d++\\.\\d++).++$#', '$1', \PCRE_VERSION);
        if (\version_compare($pcre_version, '7.2', '<')) {
            throw new \JchOptimize\Core\Exception\RuntimeException('PCRE Version less than 7.2. Exiting plugin...');
        }
        $this->params = $params;
        $this->htmlProcessor = $htmlProcessor;
        $this->cacheManager = $cacheManager;
        $this->htmlManager = $htmlManager;
        $this->http2Preload = $http2Preload;
    }

    /**
     * Optimize website by aggregating css and js.
     */
    public function process(): string
    {
        JCH_DEBUG ? Profiler::start('Process', \true) : null;

        try {
            if (!$this->html) {
                $this->logger->error('No HTML received.');

                return $this->html;
            }
            $this->htmlProcessor->setHtml($this->html);
            $this->htmlManager->preProcessHtml();
            $this->htmlProcessor->processCombineJsCss();
            $this->htmlProcessor->processImageAttributes();
            $this->cacheManager->handleCombineJsCss();
            $this->cacheManager->handleImgAttributes();
            $this->htmlProcessor->processCdn();
            $this->htmlProcessor->processLazyLoad();
            $this->htmlManager->postProcessHtml();
            $optimizedHtml = $this->reduceDom($this->minifyHtml($this->htmlProcessor->getHtml()));
            $this->sendHeaders();
            JCH_DEBUG ? Profiler::stop('Process', \true) : null;
            JCH_DEBUG ? Profiler::attachProfiler($optimizedHtml, $this->htmlProcessor->isAmpPage) : null;
        } catch (\JchOptimize\Core\Exception\ExceptionInterface $e) {
            $this->logger->error((string) $e);
            $optimizedHtml = $this->html;
        }
        if (\version_compare(\PHP_VERSION, '7.0.0', '>=')) {
            \ini_set('pcre.jit', (string) $this->jit);
        }

        return $optimizedHtml;
    }

    public function setHtml($html): void
    {
        $this->html = $html;
    }

    /**
     * If parameter is set will minify HTML before sending to browser;
     * Inline CSS and JS will also be minified if respective parameters are set.
     *
     * @return string Optimized HTML
     */
    public function minifyHtml(string $html): string
    {
        JCH_DEBUG ? Profiler::start('MinifyHtml') : null;
        if ($this->params->get('combine_files_enable', '1') && $this->params->get('html_minify', 0)) {
            $aOptions = [];
            if ($this->params->get('css_minify', 0)) {
                $aOptions['cssMinifier'] = ['CodeAlfa\\Minify\\Css', 'optimize'];
            }
            if ($this->params->get('js_minify', 0)) {
                $aOptions['jsMinifier'] = ['CodeAlfa\\Minify\\Js', 'optimize'];
            }
            $aOptions['jsonMinifier'] = ['CodeAlfa\\Minify\\Json', 'optimize'];
            $aOptions['minifyLevel'] = $this->params->get('html_minify_level', 0);
            $aOptions['isXhtml'] = \JchOptimize\Core\Helper::isXhtml($html);
            $aOptions['isHtml5'] = \JchOptimize\Core\Helper::isHtml5($html);
            $htmlMin = Html::optimize($html, $aOptions);
            if ('' == $htmlMin) {
                $this->logger->error('Error while minifying HTML');
                $htmlMin = $html;
            }
            $html = $htmlMin;
            JCH_DEBUG ? Profiler::stop('MinifyHtml', \true) : null;
        }

        return $html;
    }

    protected function reduceDom(string $html)
    {
        if (JCH_PRO) {
            /** @see ReduceDom::process() */
            $html = $this->container->get(ReduceDom::class)->process($html);
        }

        return $html;
    }

    protected function sendHeaders(): void
    {
        $headers = [];
        if ($this->http2Preload->isEnabled()) {
            $preloads = $this->http2Preload->getPreloads();
            $preloadHeaders = [];
            foreach ($preloads['link'] as $preload) {
                $preloadHeader = "<{$preload['href']}>; rel=preload; as={$preload['as']}";
                if ($preload['crossorigin']) {
                    $preloadHeader .= '; crossorigin';
                }
                if (!empty($preload['type'])) {
                    $preloadHeader .= '; type="'.$preload['type'].'"';
                }
                $preloadHeaders[] = $preloadHeader;
            }
            if (!empty($preloadHeaders)) {
                $headers['Link'] = \implode(',', $preloadHeaders);
            }
        }
        if (!empty($headers)) {
            Utility::sendHeaders($headers);
        }
    }
}
