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

namespace JchOptimize\Core;

// No direct access
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareTrait;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareTrait;
use CodeAlfa\Minify\Html;
use JchOptimize\Core\Exception\ExceptionInterface;
use JchOptimize\Core\Html\CacheManager;
use JchOptimize\Core\Html\HtmlManager;
use JchOptimize\Core\Html\HtmlProcessor;
use JchOptimize\Core\Platform\ProfilerInterface;
use JchOptimize\Core\Platform\UtilityInterface;
use JchOptimize\Core\Preloads\Http2Preload;

use function defined;
use function ini_get;
use function ini_set;
use function preg_replace;
use function version_compare;

defined('_JCH_EXEC') or die('Restricted access');

/**
 * Main plugin file
 *
 */
class Optimize implements LoggerAwareInterface, ContainerAwareInterface
{
    use LoggerAwareTrait;
    use ContainerAwareTrait;

    private string $jit = '1';

    /**
     * @throws Exception\RuntimeException
     */
    public function __construct(
        private Registry $params,
        private HtmlProcessor $htmlProcessor,
        private CacheManager $cacheManager,
        private HtmlManager $htmlManager,
        private Http2Preload $http2Preload,
        private ProfilerInterface $profiler,
        private UtilityInterface $utility
    ) {
        ini_set('pcre.backtrack_limit', '1000000');
        ini_set('pcre.recursion_limit', '1000000');

        if (version_compare(PHP_VERSION, '7.0.0', '>=')) {
            $this->jit = ini_get('pcre.jit');
            ini_set('pcre.jit', '0');
        }

        if (version_compare(PHP_VERSION, '7.3', '<')) {
            throw new Exception\RuntimeException('PHP Version less than 7.3, Exiting plugin...');
        }

        $pcre_version = preg_replace('#(^\d++\.\d++).++$#', '$1', PCRE_VERSION);

        if (version_compare($pcre_version, '7.2', '<')) {
            throw new Exception\RuntimeException('PCRE Version less than 7.2. Exiting plugin...');
        }
    }

    public function process(string $html): string
    {
        !JCH_DEBUG ?: $this->profiler->start('Process', true);

        try {
            $this->htmlProcessor->setHtml($html);

            $this->htmlManager->preProcessHtml();
            $this->htmlProcessor->processCombineJsCss();
            $this->htmlProcessor->processImageAttributes();

            $this->cacheManager->handleCombineJsCss();
            $this->cacheManager->handleImgAttributes();

            $this->htmlProcessor->processCdn();
            $this->htmlProcessor->processLazyLoad();
            $this->htmlManager->postProcessHtml();

            $optimizedHtml = $this->minifyHtml($this->htmlProcessor->getHtml());

            !JCH_DEBUG ?: $this->profiler->stop('Process', true);

            !JCH_DEBUG ?: $this->profiler->attachProfiler($optimizedHtml, $this->htmlProcessor->isAmpPage);
        } catch (ExceptionInterface $e) {
            $this->logger->error((string)$e);

            $optimizedHtml = $html;
        }

        if (version_compare(PHP_VERSION, '7.0.0', '>=')) {
            ini_set('pcre.jit', (string)$this->jit);
        }

        return $optimizedHtml;
    }

    /**
     * If parameter is set will minify HTML before sending to browser;
     * Inline CSS and JS will also be minified if respective parameters are set
     *
     * @param string $html
     *
     * @return string                       Optimized HTML
     */
    public function minifyHtml(string $html): string
    {
        !JCH_DEBUG ?: $this->profiler->start('MinifyHtml');


        if ($this->params->get('combine_files_enable', '1') && $this->params->get('html_minify', 0)) {
            $aOptions = array();

            if ($this->params->get('css_minify', 0)) {
                $aOptions['cssMinifier'] = array('CodeAlfa\Minify\Css', 'optimize');
            }

            if ($this->params->get('js_minify', 0)) {
                $aOptions['jsMinifier'] = array('CodeAlfa\Minify\Js', 'optimize');
            }

            $aOptions['jsonMinifier'] = array('CodeAlfa\Minify\Json', 'optimize');
            $aOptions['minifyLevel'] = $this->params->get('html_minify_level', 0);
            $aOptions['isXhtml'] = Helper::isXhtml($html);
            $aOptions['isHtml5'] = Helper::isHtml5($html);

            $htmlMin = Html::optimize($html, $aOptions);

            if ($htmlMin == '') {
                $this->logger->error('Error while minifying HTML');

                $htmlMin = $html;
            }

            $html = $htmlMin;

            !JCH_DEBUG ?: $this->profiler->stop('MinifyHtml', true);
        }

        return $html;
    }
}
