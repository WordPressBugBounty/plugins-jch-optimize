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

namespace JchOptimize\Core\Css;

use _JchOptimizeVendor\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareTrait;
use CodeAlfa\RegexTokenizer\Debug\Debug;
use JchOptimize\Core\Container\ContainerAwareTrait;
use JchOptimize\Core\Css\Callbacks\CombineMediaQueries;
use JchOptimize\Core\Css\Callbacks\CorrectUrls;
use JchOptimize\Core\Css\Callbacks\ExtractCriticalCss;
use JchOptimize\Core\Css\Callbacks\FormatCss;
use JchOptimize\Core\Css\Callbacks\HandleAtRules;
use JchOptimize\Core\Exception;
use JchOptimize\Core\FileInfosUtilsTrait;
use JchOptimize\Core\FileUtils;
use JchOptimize\Core\Html\Processor as HtmlProcessor;
use JchOptimize\Core\Registry;
use JchOptimize\Core\SerializableTrait;
use JchOptimize\Platform\Profiler;

\defined('_JCH_EXEC') or exit('Restricted access');
class Processor implements LoggerAwareInterface, ContainerAwareInterface, \Serializable
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;
    use Debug;
    use FileInfosUtilsTrait;
    use SerializableTrait;
    protected string $css = '';

    private Registry $params;

    private string $debugUrl = '';

    private CombineMediaQueries $combineMediaQueries;

    private CorrectUrls $correctUrls;

    private ExtractCriticalCss $extractCriticalCss;

    private FormatCss $formatCss;

    private HandleAtRules $handleAtRules;

    public function __construct(Registry $params, CombineMediaQueries $combineMediaQueries, CorrectUrls $correctUrls, ExtractCriticalCss $extractCriticalCss, FormatCss $formatCss, HandleAtRules $handleAtRules)
    {
        $this->params = $params;
        $this->combineMediaQueries = $combineMediaQueries;
        $this->correctUrls = $correctUrls;
        $this->extractCriticalCss = $extractCriticalCss;
        $this->formatCss = $formatCss;
        $this->handleAtRules = $handleAtRules;
    }

    public function setCssInfos(array $cssInfos): void
    {
        $this->combineMediaQueries->setCssInfos($cssInfos);
        $this->correctUrls->setCssInfos($cssInfos);
        $this->handleAtRules->setCssInfos($cssInfos);
        $this->fileUtils = $this->container->get(FileUtils::class);
        $this->debugUrl = $this->prepareFileUrl($cssInfos, 'css');
        // initialize debug
        $this->_debug($this->debugUrl, '', 'CssProcessorConstructor');
    }

    public function getCss(): string
    {
        return $this->css;
    }

    public function setCss(string $css): void
    {
        if (\function_exists('mb_convert_encoding')) {
            $sEncoding = \mb_detect_encoding($css);
            if (\false === $sEncoding) {
                $sEncoding = \mb_internal_encoding();
            }
            $css = \mb_convert_encoding($css, 'utf-8', $sEncoding);
        }
        $this->css = $css;
    }

    public function formatCss(): void
    {
        $oParser = new \JchOptimize\Core\Css\Parser();
        $oParser->setExcludes([\JchOptimize\Core\Css\Parser::blockCommentToken(), \JchOptimize\Core\Css\Parser::lineCommentToken(), \JchOptimize\Core\Css\Parser::cssNestedAtRulesWithCaptureValueToken()]);
        $sPrepareExcludeRegex = '\\|"(?>[^"{}]*+"?)*?[^"{}]*+"\\|';
        $oSearchObject = new \JchOptimize\Core\Css\CssSearchObject();
        $oSearchObject->setCssNestedRuleName('media', \true);
        $oSearchObject->setCssNestedRuleName('supports', \true);
        $oSearchObject->setCssNestedRuleName('document', \true);
        $oSearchObject->setCssAtRuleCriteria(\JchOptimize\Core\Css\Parser::cssAtRulesToken());
        $oSearchObject->setCssRuleCriteria('*');
        $oSearchObject->setCssCustomRule($sPrepareExcludeRegex);
        $oSearchObject->setCssCustomRule(\JchOptimize\Core\Css\Parser::cssInvalidCssToken());
        $oParser->setCssSearchObject($oSearchObject);
        $oParser->disableBranchReset();
        $this->formatCss->validCssRules = $sPrepareExcludeRegex;

        try {
            $this->css = $oParser->processMatchesWithCallback($this->css.'}', $this->formatCss);
        } catch (Exception\PregErrorException $oException) {
            $this->logger->error('FormatCss failed - '.$this->debugUrl.': '.$oException->getMessage());
        }
        $this->_debug($this->debugUrl, '', 'formatCss');
    }

    /**
     * Preload resources in CSS.
     *
     * @param string $css Css to process
     */
    public function preloadHttp2(string $css): void
    {
        $this->css = $css;
        $this->processUrls(\true);
    }

    /**
     * The path to the combined CSS files differs from the original path so relative paths to images in the files are
     * converted to absolute paths. This method is used again to preload assets found in the Critical CSS after Optimize
     * CSS Delivery is performed.
     *
     * @param bool $isHttp2 Indicates if we're doing the run to preload assets
     */
    public function processUrls(bool $isHttp2 = \false): void
    {
        $oParser = new \JchOptimize\Core\Css\Parser();
        $oSearchObject = new \JchOptimize\Core\Css\CssSearchObject();
        $oSearchObject->setCssNestedRuleName('font-face');
        $oSearchObject->setCssNestedRuleName('keyframes');
        $oSearchObject->setCssNestedRuleName('media', \true);
        $oSearchObject->setCssNestedRuleName('supports', \true);
        $oSearchObject->setCssNestedRuleName('document', \true);
        $oSearchObject->setCssRuleCriteria(\JchOptimize\Core\Css\Parser::cssUrlWithCaptureValueToken());
        $oSearchObject->setCssAtRuleCriteria(\JchOptimize\Core\Css\Parser::cssAtImportWithCaptureValueToken());
        $oParser->setCssSearchObject($oSearchObject);
        $this->correctUrls->isHttp2 = $isHttp2;

        try {
            $this->css = $oParser->processMatchesWithCallback($this->css, $this->correctUrls);
            $this->css .= $this->correctUrls->getPostCss();
        } catch (Exception\PregErrorException $oException) {
            $sPreMessage = $isHttp2 ? 'Http/2 preload failed' : 'ProcessUrls failed';
            $this->logger->error($sPreMessage.' - '.$this->debugUrl.': '.$oException->getMessage());
        }
        $this->_debug($this->debugUrl, '', 'processUrls');
    }

    public function processAtRules(): void
    {
        $oParser = new \JchOptimize\Core\Css\Parser();
        $oSearchObject = new \JchOptimize\Core\Css\CssSearchObject();
        $oSearchObject->setCssAtRuleCriteria(\JchOptimize\Core\Css\Parser::cssAtImportWithCaptureValueToken(\true));
        $oSearchObject->setCssAtRuleCriteria(\JchOptimize\Core\Css\Parser::cssAtCharsetWithCaptureValueToken());
        $oSearchObject->setCssNestedRuleName('font-face');
        $oSearchObject->setCssNestedRuleName('media', \true);
        $oParser->setCssSearchObject($oSearchObject);

        try {
            $this->css = $this->cleanEmptyMedias($oParser->processMatchesWithCallback($this->css, $this->handleAtRules));
        } catch (Exception\PregErrorException $oException) {
            $this->logger->error('ProcessAtRules failed - '.$this->debugUrl.': '.$oException->getMessage());
        }
        $this->_debug($this->debugUrl, '', 'ProcessAtRules');
    }

    public function cleanEmptyMedias($css)
    {
        $oParser = new \JchOptimize\Core\Css\Parser();
        $oParser->setExcludes([\JchOptimize\Core\Css\Parser::blockCommentToken(), '[@/]']);
        $oParser->setParseTerm('[^@/]*+');
        $oCssEmptyMediaObject = new \JchOptimize\Core\Css\CssSearchObject();
        $oCssEmptyMediaObject->setCssNestedRuleName('media', \false, \true);
        $oParser->setCssSearchObject($oCssEmptyMediaObject);

        return $oParser->replaceMatches($css, '');
    }

    public function processMediaQueries(): void
    {
        $oParser = new \JchOptimize\Core\Css\Parser();
        $oSearchObject = new \JchOptimize\Core\Css\CssSearchObject();
        $oSearchObject->setCssNestedRuleName('media');
        $oSearchObject->setCssAtRuleCriteria(\JchOptimize\Core\Css\Parser::cssAtImportWithCaptureValueToken(\true));
        $oSearchObject->setCssRuleCriteria('*');
        $oParser->setCssSearchObject($oSearchObject);
        $oParser->disableBranchReset();

        try {
            $this->css = $oParser->processMatchesWithCallback($this->css, $this->combineMediaQueries);
        } catch (Exception\PregErrorException $oException) {
            $this->logger->error('HandleMediaQueries failed - '.$this->debugUrl.': '.$oException->getMessage());
        }
        $this->_debug($this->debugUrl, '', 'handleMediaQueries');
    }

    /**
     * @param mixed $css
     * @param mixed $html
     *
     * @throws Exception\PregErrorException
     */
    public function optimizeCssDelivery($css, $html): string
    {
        !JCH_DEBUG ?: Profiler::start('OptimizeCssDelivery');
        $this->_debug('', '', 'StartCssDelivery');
        // We can't use the $html coming in as argument as that was used to generate cache key. Let's get the
        // HTML from the HTML processor
        /** @var HtmlProcessor $htmlProcessor */
        $htmlProcessor = $this->container->get(HtmlProcessor::class);
        $html = $htmlProcessor->cleanHtml();
        // Place space around HTML attributes for easy processing with XPath
        $html = \preg_replace('#\\s*=\\s*(?|"([^"]*+)"|\'([^\']*+)\'|([^\\s/>]*+))#i', '=" $1 "', $html);
        // Truncate HTML to number of elements set in params
        $sHtmlAboveFold = '';
        \preg_replace_callback('#<[a-z0-9]++[^>]*+>(?><?[^<]*+)*?(?=<[a-z0-9])#i', function ($aM) use (&$sHtmlAboveFold) {
            $sHtmlAboveFold .= $aM[0];

            return $aM[0];
        }, $html, (int) $this->params->get('elements_above_fold', '800') + 100);
        $this->_debug('', '', 'afterHtmlTruncated');
        $oDom = new \DOMDocument();
        // Load HTML in DOM
        \libxml_use_internal_errors(\true);
        $oDom->loadHtml($sHtmlAboveFold);
        \libxml_clear_errors();
        $oXPath = new \DOMXPath($oDom);
        $this->_debug('', '', 'afterLoadHtmlDom');
        $sFullHtml = $html;
        $oParser = new \JchOptimize\Core\Css\Parser();
        $oCssSearchObject = new \JchOptimize\Core\Css\CssSearchObject();
        $oCssSearchObject->setCssNestedRuleName('media', \true);
        $oCssSearchObject->setCssNestedRuleName('supports', \true);
        $oCssSearchObject->setCssNestedRuleName('document', \true);
        $oCssSearchObject->setCssNestedRuleName('font-face');
        $oCssSearchObject->setCssNestedRuleName('keyframes');
        $oCssSearchObject->setCssNestedRuleName('page');
        $oCssSearchObject->setCssNestedRuleName('font-feature-values');
        $oCssSearchObject->setCssNestedRuleName('counter-style');
        $oCssSearchObject->setCssAtRuleCriteria(\JchOptimize\Core\Css\Parser::cssAtImportWithCaptureValueToken());
        $oCssSearchObject->setCssAtRuleCriteria(\JchOptimize\Core\Css\Parser::cssAtCharsetWithCaptureValueToken());
        $oCssSearchObject->setCssAtRuleCriteria(\JchOptimize\Core\Css\Parser::cssAtNameSpaceToken());
        $oCssSearchObject->setCssRuleCriteria('.');
        $this->extractCriticalCss->htmlAboveFold = $sHtmlAboveFold;
        $this->extractCriticalCss->fullHtml = $sFullHtml;
        $this->extractCriticalCss->setDOMXPath($oXPath);
        $oParser->setCssSearchObject($oCssSearchObject);
        $sCriticalCss = $oParser->processMatchesWithCallback($css, $this->extractCriticalCss);
        $sCriticalCss = $this->cleanEmptyMedias($sCriticalCss);
        // Process Font-Face and Key frames
        $this->extractCriticalCss->isPostProcessing = \true;
        $preCss = $this->extractCriticalCss->preCss;
        $sPostCss = $oParser->processMatchesWithCallback($this->extractCriticalCss->postCss, $this->extractCriticalCss);
        !JCH_DEBUG ?: Profiler::stop('OptimizeCssDelivery', \true);

        return $preCss.$sCriticalCss.$sPostCss;
        // $this->_debug(self::cssRulesRegex(), '', 'afterCleanCriticalCss');
    }

    public function getImports(): string
    {
        return \implode($this->handleAtRules->getImports());
    }

    public function getImages(): array
    {
        return $this->correctUrls->getImages();
    }

    public function getFontFace(): array
    {
        return $this->handleAtRules->getFontFace();
    }

    public function getGFonts(): array
    {
        return $this->handleAtRules->getGFonts();
    }

    public function getPreconnects(): array
    {
        return $this->correctUrls->getPreconnects();
    }

    public function getCssBgImagesSelectors(): array
    {
        return $this->correctUrls->getCssBgImagesSelectors();
    }

    public function getLcpImages(): array
    {
        return $this->correctUrls->getLcpImages();
    }
}
