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

namespace JchOptimize\Core\Html;

use _JchOptimizeVendor\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareTrait;
use JchOptimize\Core\Cdn as CdnCore;
use JchOptimize\Core\Container\ContainerAwareTrait;
use JchOptimize\Core\Css\Parser as CssParser;
use JchOptimize\Core\Exception;
use JchOptimize\Core\FeatureHelpers\LazyLoadExtended;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Html\Callbacks\Cdn as CdnCallback;
use JchOptimize\Core\Html\Callbacks\CombineJsCss;
use JchOptimize\Core\Html\Callbacks\LazyLoad;
use JchOptimize\Core\Registry;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\Utils;
use JchOptimize\Platform\Profiler;

\defined('_JCH_EXEC') or exit('Restricted access');

/**
 * Class Processor.
 */
class Processor implements LoggerAwareInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;

    /**
     * @var bool Indicates if the page is an Amp page
     */
    public bool $isAmpPage = \false;

    /**
     * @var array Array of IMG elements requiring width/height attribute
     */
    public array $images = [];

    /**
     * @var Registry Plugin parameters
     */
    private Registry $params;

    /**
     * @var string Used to determine the end of useful string after parsing
     */
    private string $sRegexMarker = 'JCHREGEXMARKER';

    /**
     * @var string HTML being processed
     */
    private string $html = '';

    /**
     * Processor constructor.
     *
     * @param Registry $params Plugin parameters
     */
    public function __construct(Registry $params)
    {
        $this->params = $params;
    }

    /**
     * Returns the HTML being processed.
     */
    public function getHtml(): string
    {
        return $this->html;
    }

    public function setHtml(string $html): void
    {
        $this->html = $html;
        // If amp page then combine CSS and JavaScript is disabled and any feature dependent of
        // processing generated combined files, and also lazy load images.
        $this->isAmpPage = (bool) \preg_match('#<html [^>]*?(?:&\\#26A1;|\\bamp\\b)#i', $html);
    }

    public function processCombineJsCss(): void
    {
        if ($this->params->get('combine_files_enable', '1') || $this->params->get('pro_http2_push_enable', '0') || $this->params->get('remove_css', []) || $this->params->get('remove_js', [])) {
            try {
                $oParser = new \JchOptimize\Core\Html\Parser();
                $oParser->addExclude(\JchOptimize\Core\Html\Parser::htmlCommentToken());
                $oParser->addExclude(\JchOptimize\Core\Html\Parser::htmlElementToken('noscript'));
                $oParser->addExclude(\JchOptimize\Core\Html\Parser::htmlElementToken('template'));
                $oParser->addExclude(\JchOptimize\Core\Html\Parser::htmlElementToken('script'));
                $this->setUpJsCssCriteria($oParser);

                /** @var CombineJsCss $combineJsCss */
                $combineJsCss = $this->container->get(CombineJsCss::class);
                $combineJsCss->setSection('head');
                $sProcessedHeadHtml = $oParser->processMatchesWithCallback($this->getHeadHtml(), $combineJsCss);
                $this->setHeadHtml($sProcessedHeadHtml);
                if ($this->params->get('bottom_js', '0')) {
                    $combineJsCss->setSection('body');
                    $sProcessedBodyHtml = $oParser->processMatchesWithCallback($this->getBodyHtml(), $combineJsCss);
                    $this->setBodyHtml($sProcessedBodyHtml);
                }
            } catch (Exception\PregErrorException $oException) {
                $this->logger->error('CombineJsCss failed '.$oException->getMessage());
            }
        }
    }

    public function getHeadHtml(): string
    {
        \preg_match('#'.\JchOptimize\Core\Html\Parser::htmlHeadElementToken().'#i', $this->html, $aMatches);

        return $aMatches[0].$this->sRegexMarker;
    }

    public function setHeadHtml(string $sHtml): void
    {
        $sHtml = $this->cleanRegexMarker($sHtml);
        $this->html = \preg_replace('#'.\JchOptimize\Core\Html\Parser::htmlHeadElementToken().'#i', Helper::cleanReplacement($sHtml), $this->html, 1);
    }

    public function getBodyHtml(): string
    {
        \preg_match('#'.\JchOptimize\Core\Html\Parser::htmlBodyElementToken().'#si', $this->html, $aMatches);

        return $aMatches[0].$this->sRegexMarker;
    }

    public function setBodyHtml(string $sHtml): void
    {
        $sHtml = $this->cleanRegexMarker($sHtml);
        $this->html = \preg_replace('#'.\JchOptimize\Core\Html\Parser::htmlBodyElementToken().'#si', Helper::cleanReplacement($sHtml), $this->html, 1);
    }

    /**
     * @deprecated
     */
    public function isCombineFilesSet(): bool
    {
        return !Helper::isMsieLT10() && $this->params->get('combine_files_enable', '1');
    }

    public function processImagesForApi(): array
    {
        try {
            $oParser = new \JchOptimize\Core\Html\Parser();
            $oParser->addExclude(\JchOptimize\Core\Html\Parser::htmlCommentToken());
            $oParser->addExclude(\JchOptimize\Core\Html\Parser::htmlElementsToken(['script', 'noscript', 'style']));
            $oImgElement = new \JchOptimize\Core\Html\ElementObject();
            $oImgElement->bSelfClosing = \true;
            $oImgElement->setNamesArray(['img', 'source']);
            $oImgElement->setCaptureOneOrBothAttributesArray(['src', 'srcset']);
            $oParser->addElementObject($oImgElement);
            unset($oImgElement);
            $oBgElement = new \JchOptimize\Core\Html\ElementObject();
            $oBgElement->setNamesArray(['[^\\s/"\'=<>]++']);
            $oBgElement->bSelfClosing = \true;
            $oBgElement->setCaptureAttributesArray(['style']);
            // language=RegExp
            $sValueCriteriaRegex = '(?=(?>[^b>]*+b?)*?[^b>]*+(background(?:-image)?))(?=(?>[^u>]*+u?)*?[^u>]*+('.CssParser::cssUrlWithCaptureValueToken(\true).'))';
            $oBgElement->setValueCriteriaRegex(['style' => $sValueCriteriaRegex]);
            $oParser->addElementObject($oBgElement);
            unset($oBgElement);
            $matches = $oParser->findMatches($this->getBodyHtml(), \PREG_SET_ORDER);
            $images = [];
            foreach ($matches as $match) {
                if ('img' == $match[1]) {
                    if (!empty($match[4])) {
                        $images[] = $match[4];
                    }
                    if (!empty($match[5])) {
                        $srcsetImages = Helper::extractUrlsFromSrcset($match[7]);
                        foreach ($srcsetImages as $srcsetImage) {
                            $images[] = (string) $srcsetImage;
                        }
                    }
                } elseif ('source' == $match[1] && !empty($match[7])) {
                    $srcsetImages = Helper::extractUrlsFromSrcset($match[7]);
                    foreach ($srcsetImages as $srcsetImage) {
                        $images[] = (string) $srcsetImage;
                    }
                } else {
                    $images[] = $match[6];
                }
            }

            return $images;
        } catch (Exception\PregErrorException $oException) {
            $this->logger->error('ProcessApiImages failed '.$oException->getMessage());
        }

        return [];
    }

    public function processLazyLoad(): void
    {
        $bLazyLoad = $this->params->get('lazyload_enable', '0') && !$this->isAmpPage;
        if ($bLazyLoad || $this->params->get('pro_http2_push_enable', '0') || $this->params->get('pro_load_webp_images', '0') || $this->params->get('pro_load_responsive_images', '0') || $this->params->get('pro_lcp_images_enable', '0') || \JCH_DEBUG && $this->params->get('elements_above_fold_marker', '0')) {
            !\JCH_DEBUG ?: Profiler::start('LazyLoadImages');
            $sHtml = $this->getBodyHtml();
            $sAboveFoldBody = '';
            \preg_replace_callback(
                '#[^<]*+(?:<[0-9a-z!]++[^>]*+>[^<]*+(?><[^0-9a-z][^<]*+)*+)#six',
                /** @var string[] $m */
                function (array $m) use (&$sAboveFoldBody): string {
                    $sAboveFoldBody .= $m[0];

                    return $m[0];
                },
                $sHtml,
                (int) $this->params->get('elements_above_fold', 800)
            );
            $sBelowFoldHtml = \substr($sHtml, \strlen($sAboveFoldBody));
            $fullHtml = $this->getFullHtml();
            $aboveFoldHtml = \substr($fullHtml, 0, \strlen($fullHtml) - \strlen($sBelowFoldHtml)).$this->sRegexMarker;

            try {
                $http2Args = ['section' => 'above_fold', 'lazyload' => $bLazyLoad, 'parent' => ''];
                $oAboveFoldParser = new \JchOptimize\Core\Html\Parser();
                // language=RegExp
                $this->setupLazyLoadCriteria($oAboveFoldParser, 'above_fold');

                /** @var LazyLoad $http2Callback */
                $http2Callback = $this->container->get(LazyLoad::class);
                $http2Callback->setLazyLoadArgs($http2Args);
                $processedAboveFoldHtml = $oAboveFoldParser->processMatchesWithCallback($aboveFoldHtml, $http2Callback);
                $oBelowFoldParser = new \JchOptimize\Core\Html\Parser();
                $lazyLoadArgs = ['section' => 'below_fold', 'lazyload' => $bLazyLoad, 'parent' => ''];
                $this->setupLazyLoadCriteria($oBelowFoldParser, 'below_fold');

                /** @var LazyLoad $lazyLoadCallback */
                $lazyLoadCallback = $this->container->get(LazyLoad::class);
                $lazyLoadCallback->setLazyLoadArgs($lazyLoadArgs);
                $processedBelowFoldHtml = $oBelowFoldParser->processMatchesWithCallback($sBelowFoldHtml, $lazyLoadCallback);
                $marker = '';
                if (\JCH_DEBUG && $this->params->get('elements_above_fold_marker', '0')) {
                    $marker = '<span id="jch-optimize-elements-marker" style="position: relative;"><span style="position: absolute; color: red; font-size: 500px; line-height: 0px; z-index: 1000;">&#x2022;</span></span>';
                }
                $this->setFullHtml($this->cleanRegexMarker($processedAboveFoldHtml).$marker.$processedBelowFoldHtml);
            } catch (Exception\PregErrorException $oException) {
                $this->logger->error('Lazy-load failed: '.$oException->getMessage());
            }
            !\JCH_DEBUG ?: Profiler::stop('LazyLoadImages', \true);
        }
    }

    public function processImageAttributes(): void
    {
        if ($this->params->get('img_attributes_enable', '0') || JCH_PRO && $this->params->get('pro_load_responsive_images', '0')) {
            !\JCH_DEBUG ?: Profiler::start('ProcessImageAttributes');
            $oParser = new \JchOptimize\Core\Html\Parser();
            $oParser->addExclude(\JchOptimize\Core\Html\Parser::htmlCommentToken());
            $oParser->addExclude(\JchOptimize\Core\Html\Parser::htmlElementToken('script'));
            $oParser->addExclude(\JchOptimize\Core\Html\Parser::htmlElementToken('noscript'));
            $oParser->addExclude(\JchOptimize\Core\Html\Parser::htmlElementToken('textarea'));
            $oParser->addExclude(\JchOptimize\Core\Html\Parser::htmlElementToken('template'));
            $oImgElement = new \JchOptimize\Core\Html\ElementObject();
            $oImgElement->setNamesArray(['img']);
            $oImgElement->bSelfClosing = \true;
            // language=RegExp
            $oImgElement->addPosAttrCriteriaRegex('width');
            // language=RegExp
            $oImgElement->addPosAttrCriteriaRegex('height');
            $oImgElement->negateAggregatedPosCriteria = \true;
            $oImgElement->setCaptureAttributesArray(['data-src', 'src', 'width', 'height']);
            $oImgElement->addNegAttrCriteriaRegex('srcset');
            $oParser->addElementObject($oImgElement);

            try {
                $this->images = $oParser->findMatches($this->getBodyHtml());
            } catch (Exception\PregErrorException $oException) {
                $this->logger->error('Image Attributes matches failed: '.$oException->getMessage());
            }
            !\JCH_DEBUG ?: Profiler::stop('ProcessImageAttributes', \true);
        }
    }

    public function processCdn(): void
    {
        if (!$this->params->get('cookielessdomain_enable', '0') || '' == \trim($this->params->get('cookielessdomain', '')) && '' == \trim($this->params->get('pro_cookielessdomain_2', '')) && '' == \trim($this->params->get('pro_cookieless_3', ''))) {
            return;
        }
        !\JCH_DEBUG ?: Profiler::start('RunCookieLessDomain');
        $cdnCore = $this->container->get(CdnCore::class);
        $staticFiles = $cdnCore->getCdnFileTypes();
        $sf = \implode('|', $staticFiles);
        $oUri = SystemUri::currentUri();
        $port = $oUri->getPort();
        if (empty($port)) {
            $port = ':80';
        }
        $host = '(?:www\\.)?'.\preg_quote(\preg_replace('#^www\\.#i', '', $oUri->getHost()), '#').'(?:'.$port.')?';
        // Find base value in HTML
        $oBaseParser = new \JchOptimize\Core\Html\Parser();
        $oBaseElement = new \JchOptimize\Core\Html\ElementObject();
        $oBaseElement->setNamesArray(['base']);
        $oBaseElement->bSelfClosing = \true;
        $oBaseElement->setCaptureAttributesArray(['href']);
        $oBaseParser->addElementObject($oBaseElement);

        try {
            $aMatches = $oBaseParser->findMatches($this->getHeadHtml());
        } catch (Exception\PregErrorException $e) {
            $aMatches = [];
        }
        unset($oBaseParser, $oBaseElement);

        $baseUri = SystemUri::currentUri();
        // Adjust $dir if necessary based on <base/>
        if (!empty($aMatches[0])) {
            $uri = Utils::uriFor($aMatches[4][0]);
            if ('' != (string) $uri) {
                $baseUri = $uri;
            }
        }
        // This part should match the scheme and host of a local file
        // language=RegExp
        $localhost = '(?:\\s*+(?:(?>https?:)?//'.$host.')?)(?!http|//)';
        // language=RegExp
        $valueMatch = '(?!data:image)(?='.$localhost.')(?=((?<=")(?>\\.?[^.>"?]*+)*?\\.(?>'.$sf.')(?:[?\\#][^>"]*+)?(?=")|(?<=\')(?>\\.?[^.>\'?]*+)*?\\.(?>'.$sf.')(?:[?\\#][^>\']*+)?(?=\')|(?<=\\()(?>\\.?[^.>)?]*+)*?\\.(?>'.$sf.')(?:[?\\#][^>)]*+)?(?=\\))|(?<=^|[=\\s,])(?>\\.?[^.>\\s?]*+)*?\\.(?>'.$sf.')(?:[?\\#][^>\\s]*+)?(?=[\\s>]|$)))';

        try {
            // Get regex for <script> without src attribute
            $oElementParser = new \JchOptimize\Core\Html\Parser();
            $oElementWithCriteria = new \JchOptimize\Core\Html\ElementObject();
            $oElementWithCriteria->setNamesArray(['script']);
            $oElementWithCriteria->addNegAttrCriteriaRegex('src');
            $oElementParser->addElementObject($oElementWithCriteria);
            $sScriptWithoutSrc = $oElementParser->getElementWithCriteria();
            unset($oElementParser, $oElementWithCriteria);

            // Process cdn for elements with href or src attributes
            $oSrcHrefParser = new \JchOptimize\Core\Html\Parser();
            $oSrcHrefParser->addExclude(\JchOptimize\Core\Html\Parser::htmlCommentToken());
            $oSrcHrefParser->addExclude($sScriptWithoutSrc);
            $this->setUpCdnSrcHrefCriteria($oSrcHrefParser, $valueMatch);

            /** @var CdnCallback $cdnCallback */
            $cdnCallback = $this->container->get(CdnCallback::class);
            $cdnCallback->setBaseUri($baseUri);
            $cdnCallback->setLocalhost($host);
            $cdnCallback->setSearchRegex($valueMatch);
            $sCdnHtml = $oSrcHrefParser->processMatchesWithCallback($this->getFullHtml(), $cdnCallback);
            unset($oSrcHrefParser);
            $this->setFullHtml($sCdnHtml);
            // Process cdn for CSS urls in style attributes or <style/> elements
            // language=RegExp
            $sUrlSearchRegex = '(?=((?>[()]?[^()<>]*+)*?(?<=url)\\((?>[\'"]?\\s*+)'.$valueMatch.'))';
            $oUrlParser = new \JchOptimize\Core\Html\Parser();
            $oUrlParser->addExclude(\JchOptimize\Core\Html\Parser::htmlCommentToken());
            $oUrlParser->addExclude(\JchOptimize\Core\Html\Parser::htmlElementsToken(['script']));
            $this->setUpCdnUrlCriteria($oUrlParser, $sUrlSearchRegex);
            $cdnCallback->setContext('cssurl');
            $sCdnUrlHtml = $oUrlParser->processMatchesWithCallback($this->getFullHtml(), $cdnCallback);
            unset($oUrlParser);
            $this->setFullHtml($sCdnUrlHtml);
            // Process cdn for elements with srcset attributes
            $oSrcsetParser = new \JchOptimize\Core\Html\Parser();
            $oSrcsetParser->addExclude(\JchOptimize\Core\Html\Parser::htmlCommentToken());
            $oSrcsetParser->addExclude(\JchOptimize\Core\Html\Parser::htmlElementToken('script'));
            $oSrcsetParser->addExclude(\JchOptimize\Core\Html\Parser::htmlElementToken('style'));
            $oSrcsetElement = new \JchOptimize\Core\Html\ElementObject();
            $oSrcsetElement->bSelfClosing = \true;
            $oSrcsetElement->setNamesArray(['img', 'source']);
            $oSrcsetElement->setCaptureOneOrBothAttributesArray(['srcset', 'data-srcset']);
            $oSrcsetElement->setValueCriteriaRegex('(?=.)');
            $oSrcsetParser->addElementObject($oSrcsetElement);
            $cdnCallback->setContext('srcset');
            $sCdnSrcsetHtml = $oSrcsetParser->processMatchesWithCallback($this->getBodyHtml(), $cdnCallback);
            unset($oSrcsetParser, $oSrcsetElement);

            $this->setBodyHtml($sCdnSrcsetHtml);
        } catch (Exception\PregErrorException $oException) {
            $this->logger->error('Cdn failed :'.$oException->getMessage());
        }
        !\JCH_DEBUG ?: Profiler::stop('RunCookieLessDomain', \true);
    }

    public function getFullHtml(): string
    {
        return $this->html.$this->sRegexMarker;
    }

    public function setFullHtml(string $sHtml): void
    {
        $this->html = $this->cleanRegexMarker($sHtml);
    }

    public function processModulesForPreload(): array
    {
        try {
            $parser = new \JchOptimize\Core\Html\Parser();
            $parser->addExclude(\JchOptimize\Core\Html\Parser::htmlCommentToken());
            $parser->addExclude(\JchOptimize\Core\Html\Parser::htmlElementToken('noscript'));
            $element = new \JchOptimize\Core\Html\ElementObject();
            $element->setNamesArray(['script']);
            $element->addPosAttrCriteriaRegex('type==[\'"]?module');
            $element->setCaptureAttributesArray(['src']);
            $element->setValueCriteriaRegex('(?=.)');
            $parser->addElementObject($element);

            return $parser->findMatches($this->getFullHtml(), \PREG_PATTERN_ORDER);
        } catch (Exception\PregErrorException $e) {
            $this->logger->error('ProcessModulesForPreload feiled '.$e->getMessage());
        }

        return [];
    }

    public function processDataFromCacheScriptToken(string $token): void
    {
        try {
            $parser = new \JchOptimize\Core\Html\Parser();
            $element = new \JchOptimize\Core\Html\ElementObject();
            $element->setNamesArray(['script']);
            $element->addPosAttrCriteriaRegex('type==(?>[\'"]?)application/(?:ld\\+)?json');
            $element->addPosAttrCriteriaRegex('class==(?>[\'"]?)[^\'"<>]*?joomla-script-options');
            $parser->addElementObject($element);
            $headHtml = $this->getHeadHtml();
            $matches = $parser->findMatches($this->getHeadHtml());
            if (!empty($matches[0])) {
                $tokenized = \preg_replace('#"csrf.token":"\\K[^"]++#', $token, $matches[0]);
                $newHeadHtml = \str_replace($matches[0], $tokenized, $headHtml);
                $this->setHeadHtml($newHeadHtml);
            }
        } catch (Exception\PregErrorException $e) {
            $this->logger->error('ProcessDataFromCache failed '.$e->getMessage());
        }
    }

    public function cleanHtml(): string
    {
        $aSearch = ['#'.\JchOptimize\Core\Html\Parser::htmlHeadElementToken().'#ix', '#'.\JchOptimize\Core\Html\Parser::htmlCommentToken().'#ix', '#'.\JchOptimize\Core\Html\Parser::htmlElementToken('script').'#ix', '#'.\JchOptimize\Core\Html\Parser::htmlElementToken('style').'#ix', '#'.\JchOptimize\Core\Html\Parser::htmlElementToken('link', \true).'#six'];
        $aReplace = ['<head><title></title></head>', '', '', '', ''];
        $html = \preg_replace($aSearch, $aReplace, $this->html);
        // Remove any hidden element from HtmL
        $html = \preg_replace_callback('#(<[^>]*+>)[^<>]*+#ix', function ($aMatches) {
            if (\preg_match('#type\\s*+=\\s*+["\']?hidden["\'\\s>]|\\shidden(?=[\\s>=])[^>\'"=]*+[>=]#i', $aMatches[1])) {
                return '';
            }
            // Add linebreak for readability during debugging
            return $aMatches[1]."\n";
        }, $html);
        // Remove Text nodes
        // Remove text nodes from HTML elements
        return \preg_replace_callback('#(<(?>[^<>]++|(?1))*+>)|((?<=>)(?=[^<>\\S]*+[^<>\\s])[^<>]++)#', function ($m) {
            if (!empty($m[1])) {
                return $m[0];
            }
            if (!empty($m[2])) {
                return ' ';
            }

            return '';
        }, $html);
    }

    protected function setUpJsCssCriteria(Parser $oParser): void
    {
        $oJsFilesElement = new \JchOptimize\Core\Html\ElementObject();
        $oJsFilesElement->setNamesArray(['script']);
        // language=RegExp
        $oJsFilesElement->addNegAttrCriteriaRegex('type==(?!(?>[\'"]?)(?:(?:text|application)/javascript|module)[\'"> ])');
        $oJsFilesElement->setCaptureAttributesArray(['src']);
        $oJsFilesElement->setValueCriteriaRegex('(?=.)');
        $oParser->addElementObject($oJsFilesElement);
        $oJsContentElement = new \JchOptimize\Core\Html\ElementObject();
        $oJsContentElement->setNamesArray(['script']);
        // language=RegExp
        $oJsContentElement->addNegAttrCriteriaRegex('src|type==(?!(?>[\'"]?)(?:(?:text|application)/javascript|module)[\'"> ])');
        $oJsContentElement->bCaptureContent = \true;
        $oParser->addElementObject($oJsContentElement);
        $oCssFileElement = new \JchOptimize\Core\Html\ElementObject();
        $oCssFileElement->bSelfClosing = \true;
        $oCssFileElement->setNamesArray(['link']);
        // language=RegExp
        $oCssFileElement->addNegAttrCriteriaRegex('itemprop|disabled|type==(?!(?>[\'"]?)text/css[\'"> ])|rel==(?!(?>[\'"]?)stylesheet[\'"> ])');
        $oCssFileElement->setCaptureAttributesArray(['href']);
        $oCssFileElement->setValueCriteriaRegex('(?=.)');
        $oParser->addElementObject($oCssFileElement);
        $oStyleElement = new \JchOptimize\Core\Html\ElementObject();
        $oStyleElement->setNamesArray(['style']);
        // language=RegExp
        $oStyleElement->addNegAttrCriteriaRegex('scope|amp|type==(?!(?>[\'"]?)text/(?:css|stylesheet)[\'"> ])');
        $oStyleElement->bCaptureContent = \true;
        $oParser->addElementObject($oStyleElement);
    }

    protected function cleanRegexMarker(string $sHtml): ?string
    {
        return \preg_replace('#'.\preg_quote($this->sRegexMarker, '#').'.*+$#', '', $sHtml);
    }

    protected function setupLazyLoadCriteria(Parser $oParser, bool $bDeferred): void
    {
        $oParser->addExclude(\JchOptimize\Core\Html\Parser::htmlCommentToken());
        $oParser->addExclude(\JchOptimize\Core\Html\Parser::htmlElementToken('script'));
        $oParser->addExclude(\JchOptimize\Core\Html\Parser::htmlElementToken('noscript'));
        $oParser->addExclude(\JchOptimize\Core\Html\Parser::htmlElementToken('textarea'));
        $oParser->addExclude(\JchOptimize\Core\Html\Parser::htmlElementToken('template'));
        $oImgElement = new \JchOptimize\Core\Html\ElementObject();
        $oImgElement->bSelfClosing = \true;
        $oImgElement->setNamesArray(['img']);
        // language=RegExp
        $oImgElement->addNegAttrCriteriaRegex('(?:data-(?:original-)?src)');
        $oImgElement->setCaptureAttributesArray(['class', 'src', 'srcset', '(?:data-)?width', '(?:data-)?height']);
        $oParser->addElementObject($oImgElement);
        unset($oImgElement);
        $oInputElement = new \JchOptimize\Core\Html\ElementObject();
        $oInputElement->bSelfClosing = \true;
        $oInputElement->setNamesArray(['input']);
        // language=RegExp
        $oInputElement->addPosAttrCriteriaRegex('type=(?>[\'"]?)image[\'"> ]');
        $oInputElement->setCaptureAttributesArray(['class', 'src']);
        $oParser->addElementObject($oInputElement);
        unset($oInputElement);
        $oPictureElement = new \JchOptimize\Core\Html\ElementObject();
        $oPictureElement->setNamesArray(['picture']);
        $oPictureElement->setCaptureAttributesArray(['class']);
        $oPictureElement->bCaptureContent = \true;
        $oParser->addElementObject($oPictureElement);
        unset($oPictureElement);
        if (JCH_PRO) {
            // @see LazyLoadExtended::setupLazyLoadExtended()
            $this->container->get(LazyLoadExtended::class)->setupLazyLoadExtended($oParser, $bDeferred);
        }
    }

    protected function setUpCdnSrcHrefCriteria(Parser $oParser, string $sValueMatch): void
    {
        $oSrcElement = new \JchOptimize\Core\Html\ElementObject();
        $oSrcElement->bSelfClosing = \true;
        $oSrcElement->setNamesArray(['img', 'script', 'source', 'input']);
        $oSrcElement->setCaptureOneOrBothAttributesArray(['src', 'data-src']);
        $oSrcElement->setValueCriteriaRegex($sValueMatch);
        $oParser->addElementObject($oSrcElement);
        unset($oSrcElement);
        $oHrefElement = new \JchOptimize\Core\Html\ElementObject();
        $oHrefElement->bSelfClosing = \true;
        $oHrefElement->setNamesArray(['a', 'link', 'image']);
        $oHrefElement->setCaptureAttributesArray(['(?:xlink:)?href']);
        $oHrefElement->setValueCriteriaRegex($sValueMatch);
        $oParser->addElementObject($oHrefElement);
        unset($oHrefElement);
        $oVideoElement = new \JchOptimize\Core\Html\ElementObject();
        $oVideoElement->bSelfClosing = \true;
        $oVideoElement->setNamesArray(['video']);
        $oVideoElement->setCaptureAttributesArray(['(?:src|poster)']);
        $oVideoElement->setValueCriteriaRegex($sValueMatch);
        $oParser->addElementObject($oVideoElement);
        unset($oVideoElement);
        $oMediaElement = new \JchOptimize\Core\Html\ElementObject();
        $oMediaElement->bSelfClosing = \true;
        $oMediaElement->setNamesArray(['meta']);
        $oMediaElement->setCaptureAttributesArray(['content']);
        $oMediaElement->setValueCriteriaRegex($sValueMatch);
        $oParser->addElementObject($oMediaElement);
        unset($oMediaElement);
    }

    protected function setUpCdnUrlCriteria(Parser $oParser, string $sValueMatch): void
    {
        $oElements = new \JchOptimize\Core\Html\ElementObject();
        $oElements->bSelfClosing = \true;
        // language=RegExp
        $oElements->setNamesArray(['(?!style|script|link|meta)[^\\s/"\'=<>]++']);
        $oElements->setCaptureAttributesArray(['style']);
        $oElements->setValueCriteriaRegex($sValueMatch);
        $oParser->addElementObject($oElements);
        unset($oElements);
        $oStyleElement = new \JchOptimize\Core\Html\ElementObject();
        $oStyleElement->setNamesArray(['style']);
        $oStyleElement->bCaptureContent = \true;
        $oStyleElement->setValueCriteriaRegex($sValueMatch);
        $oParser->addElementObject($oStyleElement);
        unset($oStyleElement);
    }
}
