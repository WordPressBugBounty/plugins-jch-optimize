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

namespace JchOptimize\Core\Html;

use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareTrait;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareTrait;
use JchOptimize\Core\Cdn\Cdn as CdnCore;
use JchOptimize\Core\Css\Components\CssUrl;
use JchOptimize\Core\Css\Parser as CssParser;
use JchOptimize\Core\Exception\InvalidArgumentException;
use JchOptimize\Core\Exception\PregErrorException;
use JchOptimize\Core\FeatureHelpers\LazyLoadExtended;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Html\Callbacks\Cdn as CdnCallback;
use JchOptimize\Core\Html\Callbacks\CombineJsCss;
use JchOptimize\Core\Html\Callbacks\JavaScriptConfigureHelper;
use JchOptimize\Core\Html\Callbacks\LazyLoad;
use JchOptimize\Core\Html\Elements\Img;
use JchOptimize\Core\Html\Elements\Source;
use JchOptimize\Core\Platform\ProfilerInterface;
use JchOptimize\Core\Registry;
use JchOptimize\Core\SystemUri;

use function defined;
use function implode;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function str_replace;
use function strlen;
use function substr;
use function trim;

use const JCH_DEBUG;
use const JCH_PRO;
use const PREG_SET_ORDER;

defined('_JCH_EXEC') or die('Restricted access');

/**
 * Class Processor
 *
 * @package JchOptimize\Core\Html
 *
 * This class interacts with the Parser passing over HTML elements, criteria and callbacks to parse for in the HTML
 * and maintains the processed HTML
 */
class HtmlProcessor implements LoggerAwareInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;

    public bool $isAmpPage = false;

    /**
     * @var array $images Array of IMG elements requiring width/height attribute
     */
    public array $images = [];

    /**
     * @var string         Used to determine the end of useful string after parsing
     */
    private string $regexMarker = 'JCHREGEXMARKER';

    /**
     * @var string         HTML being processed
     */
    private string $html = '';

    public function __construct(private Registry $params, private ProfilerInterface $profiler)
    {
    }

    /**
     * Returns the HTML being processed
     */
    public function getHtml(): string
    {
        return $this->html;
    }

    public function setHtml(string $html): void
    {
        $this->html = $html;
        //If amp page then combine CSS and JavaScript is disabled and any feature dependent of
        // processing generated combined files, and also lazy load images.
        $this->isAmpPage = (bool)preg_match('#<html [^>]*?(?:&\#26A1;|\bamp\b)#i', $html);
    }

    public function processCombineJsCss(): void
    {
        if (
            $this->params->get('combine_files_enable', '1')
            || $this->params->get('http2_push_enable', '0')
            || $this->params->get('remove_css', [])
            || $this->params->get('remove_js', [])
        ) {
            try {
                $oParser = new Parser();
                $oParser->addExcludes(['noscript', 'template', 'script']);
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
            } catch (PregErrorException $oException) {
                $this->logger?->error('CombineJsCss failed ' . $oException->getMessage());
            }

            !JCH_DEBUG ?: $this->profiler->stop('CombineJsCss', true);
        }
    }

    protected function setUpJsCssCriteria(Parser $oParser): void
    {
        $oJsFilesElement = new ElementObject();
        $oJsFilesElement->setNamesArray(['script']);
        $oJsFilesElement->addNegAttrCriteriaRegex(
            'type!=(?:(?:text|application)/javascript|module)'
        );
        $oParser->addElementObject($oJsFilesElement);

        $oCssFileElement = new ElementObject();
        $oCssFileElement->voidElementOrStartTagOnly = true;
        $oCssFileElement->setNamesArray(['link']);
        $oCssFileElement->addNegAttrCriteriaRegex(
            'itemprop||disabled||type!=text/css||rel!=stylesheet'
        );
        $oParser->addElementObject($oCssFileElement);

        $oStyleElement = new ElementObject();
        $oStyleElement->setNamesArray(['style']);
        $oStyleElement->addNegAttrCriteriaRegex('scope||amp||type!=(?:text/(?:css|stylesheet))');
        $oParser->addElementObject($oStyleElement);
    }

    /**
     * @throws PregErrorException
     */
    public function getHeadHtml(): string
    {
        preg_match('#' . Parser::htmlHeadElementToken() . '#i', $this->html, $aMatches);

        Parser::throwExceptionOnPregError();

        return $aMatches[0] . $this->regexMarker;
    }

    /**
     * @throws PregErrorException
     */
    public function setHeadHtml(string $sHtml): void
    {
        $sHtml = $this->cleanRegexMarker($sHtml);
        $this->html = preg_replace(
            '#' . Parser::htmlHeadElementToken() . '#i',
            Helper::cleanReplacement($sHtml),
            $this->html,
            1
        );

        Parser::throwExceptionOnPregError();
    }

    protected function cleanRegexMarker(string $html): string
    {
        $cleanedHtml = preg_replace('#' . preg_quote($this->regexMarker, '#') . '.*+$#', '', $html);

        if ($cleanedHtml === null) {
            return $html;
        }

        return $cleanedHtml;
    }

    /**
     * @throws PregErrorException
     */
    public function getBodyHtml(): string
    {
        preg_match('#' . Parser::htmlBodyElementToken() . '#si', $this->html, $aMatches);

        Parser::throwExceptionOnPregError();

        return $aMatches[0] . $this->regexMarker;
    }

    /**
     * @throws PregErrorException
     */
    public function setBodyHtml(string $sHtml): void
    {
        $sHtml = $this->cleanRegexMarker($sHtml);
        $this->html = preg_replace(
            '#' . Parser::htmlBodyElementToken() . '#si',
            Helper::cleanReplacement($sHtml),
            $this->html,
            1
        );

        Parser::throwExceptionOnPregError();
    }

    public function processImagesForApi(): array
    {
        try {
            $oParser = new Parser();
            $oParser->addExcludes(['script', 'noscript', 'style']);

            $oImgElement = new ElementObject();
            $oImgElement->voidElementOrStartTagOnly = true;
            $oImgElement->setNamesArray(['img', 'source']);
            $oImgElement->addPosAttrCriteriaRegex('src(?:set)?');
            $oParser->addElementObject($oImgElement);
            unset($oImgElement);

            $oBgElement = new ElementObject();
            $oBgElement->voidElementOrStartTagOnly = true;
            $oBgElement->addPosAttrCriteriaRegex('style*=' . CssParser::cssUrlToken());
            $oParser->addElementObject($oBgElement);
            unset($oBgElement);

            $matches = $oParser->findMatches($this->getBodyHtml(), PREG_SET_ORDER);
            $images = [];

            foreach ($matches as $match) {
                $element = HtmlElementBuilder::load($match[0]);

                if ($element instanceof Img) {
                    $src = $element->getSrc();

                    if ($src instanceof UriInterface) {
                        $images[] = (string)$src;
                    }

                    $srcset = $element->attributeValue('srcset');

                    if ($srcset !== false) {
                        $srcsetImages = Helper::extractUrlsFromSrcset($srcset);

                        foreach ($srcsetImages as $srcsetImage) {
                            $images[] = (string)$srcsetImage;
                        }
                    }
                } elseif ($element instanceof Source) {
                    $srcset = $element->attributeValue('srcset');

                    if ($srcset !== false) {
                        $srcsetImages = Helper::extractUrlsFromSrcset($srcset);
                        foreach ($srcsetImages as $srcsetImage) {
                            $images[] = (string)$srcsetImage;
                        }
                    }
                } else {
                    $style = $element->getStyle();

                    if ($style !== false) {
                        if (preg_match('#' . CssParser::cssUrlToken() . '#i', $style, $matches)) {
                            try {
                                $cssUrl = CssUrl::load($matches[0]);
                                $images[] = (string)$cssUrl->getUri();
                            } catch (InvalidArgumentException) {
                                continue;
                            }
                        }
                    }
                }
            }

            return $images;
        } catch (PregErrorException $oException) {
            $this->logger?->error('ProcessApiImages failed ' . $oException->getMessage());
        }

        return [];
    }

    /**
     * @throws PregErrorException
     */
    public function processLazyLoad(): void
    {
        $lazyLoadFlag = $this->params->get('lazyload_enable', '0') && !$this->isAmpPage;

        if (
            $lazyLoadFlag
            || $this->params->get('http2_push_enable', '0')
            || $this->params->get('pro_load_webp_images', '0')
            || $this->params->get('pro_load_responsive_images', '0')
            || $this->params->get('pro_lcp_images_enable', '0')
            || (JCH_DEBUG && $this->params->get('elements_above_fold_marker', '0'))
        ) {
            !JCH_DEBUG ?: $this->profiler->start('LazyLoadImages');

            $html = $this->getBodyHtml();

            $aboveFoldBody = $this->getAboveFoldHtml($html);
            $belowFoldHtml = substr($html, strlen($aboveFoldBody));
            $fullHtml = $this->getFullHtml();
            $aboveFoldHtml = substr($fullHtml, 0, strlen($fullHtml) - strlen($belowFoldHtml)) . $this->regexMarker;

            try {
                $http2Args = [
                    'section' => 'above_fold',
                    'lazyload' => $lazyLoadFlag,
                    'parent' => ''
                ];

                $aboveFoldParser = new Parser();
                //language=RegExp
                $this->setupLazyLoadCriteria($aboveFoldParser, 'above_fold', $lazyLoadFlag);
                /** @var LazyLoad $http2Callback */
                $http2Callback = $this->getContainer()->get(LazyLoad::class);
                $http2Callback->setLazyLoadArgs($http2Args);
                $processedAboveFoldHtml = $aboveFoldParser->processMatchesWithCallback(
                    $aboveFoldHtml,
                    $http2Callback
                );

                $belowFoldParser = new Parser();
                $lazyLoadArgs = [
                    'section' => 'below_fold',
                    'lazyload' => $lazyLoadFlag,
                    'parent' => '',
                ];

                $this->setupLazyLoadCriteria($belowFoldParser, 'below_fold', $lazyLoadFlag);
                /** @var LazyLoad $lazyLoadCallback */
                $lazyLoadCallback = $this->getContainer()->get(LazyLoad::class);
                $lazyLoadCallback->setLazyLoadArgs($lazyLoadArgs);
                $processedBelowFoldHtml = $belowFoldParser->processMatchesWithCallback(
                    $belowFoldHtml,
                    $lazyLoadCallback
                );

                $marker = '';

                if (JCH_DEBUG && $this->params->get('elements_above_fold_marker', '0')) {
                    $marker = <<<HTML
<span id="jchoptimize-elements-marker" style="position: relative;">
</span>
HTML;
                }

                $this->setFullHtml(
                    $this->cleanRegexMarker($processedAboveFoldHtml) . $marker . $processedBelowFoldHtml
                );
            } catch (PregErrorException $oException) {
                $this->logger?->error('Lazy-load failed: ' . $oException->getMessage());
            }

            !JCH_DEBUG ?: $this->profiler->stop('LazyLoadImages', true);
        }
    }

    protected function setupLazyLoadCriteria(Parser $oParser, string $bDeferred, bool $lazyLoad): void
    {
        $oParser->addExcludes(['script', 'noscript', 'textarea']);

        $oImgElement = new ElementObject();
        $oImgElement->voidElementOrStartTagOnly = true;
        $oImgElement->setNamesArray(['img']);
        $oImgElement->addNegAttrCriteriaRegex('(?:data|original)-src');
        $oParser->addElementObject($oImgElement);
        unset($oImgElement);

        $oInputElement = new ElementObject();
        $oInputElement->voidElementOrStartTagOnly = true;
        $oInputElement->setNamesArray(['input']);
        $oInputElement->addPosAttrCriteriaRegex('type==image');
        $oParser->addElementObject($oInputElement);
        unset($oInputElement);

        $oPictureElement = new ElementObject();
        $oPictureElement->setNamesArray(['picture']);
        $oParser->addElementObject($oPictureElement);
        unset($oPictureElement);

        if (JCH_PRO) {
            /** @see LazyLoadExtended::setupLazyLoadExtended() */
            $this->getContainer()->get(LazyLoadExtended::class)->setupLazyLoadExtended($oParser, $bDeferred, $lazyLoad);
        }
    }

    public function processImageAttributes(): void
    {
        if (
            $this->params->get('img_attributes_enable', '0')
            /*|| ($this->params->get('lazyload_enable', '0')
                && $this->params->get('lazyload_autosize', '0')) */
            || JCH_PRO && $this->params->get('pro_load_responsive_images', '0')
            || JCH_PRO && $this->params->get('pro_lcp_images_enable', '0')
        ) {
            !JCH_DEBUG ?: $this->profiler->start('ProcessImageAttributes');

            $oParser = new Parser();

            $oImgElement = new ElementObject();
            $oImgElement->setNamesArray(['img']);
            $oImgElement->voidElementOrStartTagOnly = true;
            $oImgElement->addNegAttrCriteriaRegex([
                ['pos' => 'width'],
                ['pos' => 'height']
            ]);
            //$oImgElement->addNegAttrCriteriaRegex('srcset');
            $oParser->addElementObject($oImgElement);

            try {
                $this->images = $oParser->findMatches($this->getBodyHtml());
            } catch (PregErrorException $oException) {
                $this->logger?->error('Image Attributes matches failed: ' . $oException->getMessage());
            }

            !JCH_DEBUG ?: $this->profiler->stop('ProcessImageAttributes', true);
        }
    }

    public function processCdn(): void
    {
        if (
            !$this->params->get('cookielessdomain_enable', '0') ||
            (trim($this->params->get('cookielessdomain', '')) == '' &&
                trim($this->params->get('pro_cookielessdomain_2', '')) == '' &&
                trim($this->params->get('pro_cookieless_3', '')) == '')
        ) {
            return;
        }

        !JCH_DEBUG ?: $this->profiler->start('RunCookieLessDomain');

        $cdnCore = $this->getContainer()->get(CdnCore::class);

        $staticFiles = $cdnCore->getCdnFileTypes();
        $sf = implode('|', $staticFiles);
        $oUri = SystemUri::currentUri();
        $port = $oUri->getPort();

        if (empty($port)) {
            $port = ':80';
        }

        $host = '(?:www\.)?' . preg_quote(preg_replace('#^www\.#i', '', $oUri->getHost()), '#') . '(?:' . $port . ')?';
        //Find base value in HTML
        $oBaseParser = new Parser();
        $oBaseElement = new ElementObject();
        $oBaseElement->setNamesArray(['base']);
        $oBaseElement->voidElementOrStartTagOnly = true;
        $oBaseElement->addPosAttrCriteriaRegex('href');
        $oBaseParser->addElementObject($oBaseElement);

        try {
            $aMatches = $oBaseParser->findMatches($this->getHeadHtml());
        } catch (PregErrorException) {
            $aMatches = [];
        }

        unset($oBaseParser);
        unset($oBaseElement);

        $baseUri = SystemUri::currentUri();

        //Adjust $dir if necessary based on <base/>
        if (!empty($aMatches[0])) {
            try {
                $baseElementHtml = HtmlElementBuilder::load($aMatches[0][0]);
                $uri = $baseElementHtml->attributeValue('href');
            } catch (PregErrorException) {
                $uri = '';
            }

            if ((string)$uri != '') {
                $baseUri = $uri;
            }
        }

        //This part should match the scheme and host of a local file
        //language=RegExp
        $localhost = '(?:\s*+(?:(?>https?:)?//' . $host . ')?)(?!http|//)';
        //language=RegExp
        $valueMatch = '(?!data:image)'
            . '(?=' . $localhost . ')'
            . '(?=((?<=")(?>\.?[^.>"?]*+)*?\.(?>' . $sf . ')(?:[?\#][^>"]*+)?(?=")'
            . '|(?<=\')(?>\.?[^.>\'?]*+)*?\.(?>' . $sf . ')(?:[?\#][^>\']*+)?(?=\')'
            . '|(?<=\()(?>\.?[^.>)?]*+)*?\.(?>' . $sf . ')(?:[?\#][^>)]*+)?(?=\))'
            . '|(?<=^|[=\s,])(?>\.?[^.>\s?]*+)*?\.(?>' . $sf . ')(?:[?\#][^>\s]*+)?(?=[\s>]|$)))';

        try {
            //Process cdn for elements with href or src attributes
            $oSrcHrefParser = new Parser();
            $oSrcHrefParser->addExcludes(['script']);
            $this->setUpCdnSrcHrefCriteria($oSrcHrefParser);

            /** @var CdnCallback $cdnCallback */
            $cdnCallback = $this->getContainer()->get(CdnCallback::class);
            $cdnCallback->setBaseUri($baseUri);
            $cdnCallback->setLocalhost($host);
            $cdnCallback->setSearchRegex($valueMatch);
            $sCdnHtml = $oSrcHrefParser->processMatchesWithCallback($this->getFullHtml(), $cdnCallback);
            unset($oSrcHrefParser);

            $this->setFullHtml($sCdnHtml);

            //Process cdn for CSS urls in style attributes or <style/> elements
            $oUrlParser = new Parser();
            $oUrlParser->addExcludes(['script']);
            $this->setUpCdnUrlCriteria($oUrlParser);
            $cdnCallback->setContext('cssurl');
            $sCdnUrlHtml = $oUrlParser->processMatchesWithCallback($this->getFullHtml(), $cdnCallback);
            unset($oUrlParser);

            $this->setFullHtml($sCdnUrlHtml);

            //Process cdn for elements with srcset attributes
            $oSrcsetParser = new Parser();
            $oSrcsetParser->addExcludes(['script', 'style']);

            $oSrcsetElement = new ElementObject();
            $oSrcsetElement->voidElementOrStartTagOnly = true;
            $oSrcsetElement->setNamesArray(['img', 'source']);
            $oSrcsetElement->addPosAttrCriteriaRegex('(?:data-)?srcset');

            $oSrcsetParser->addElementObject($oSrcsetElement);
            $cdnCallback->setContext('srcset');
            $sCdnSrcsetHtml = $oSrcsetParser->processMatchesWithCallback($this->getBodyHtml(), $cdnCallback);
            unset($oSrcsetParser);
            unset($oSrcsetElement);

            $this->setBodyHtml($sCdnSrcsetHtml);
        } catch (PregErrorException $oException) {
            $this->logger?->error('Cdn failed :' . $oException->getMessage());
        }

        !JCH_DEBUG ?: $this->profiler->stop('ProcessCDN', true);
    }

    protected function setUpCdnSrcHrefCriteria(Parser $oParser): void
    {
        $oSrcElement = new ElementObject();
        $oSrcElement->voidElementOrStartTagOnly = true;
        $oSrcElement->setNamesArray(['img', 'script', 'source', 'input']);
        $oSrcElement->addPosAttrCriteriaRegex('(?:data-)?src');
        $oParser->addElementObject($oSrcElement);
        unset($oSrcElement);

        $oHrefElement = new ElementObject();
        $oHrefElement->voidElementOrStartTagOnly = true;
        $oHrefElement->setNamesArray(['a', 'link', 'image']);
        $oHrefElement->addPosAttrCriteriaRegex('(?:xlink:)?href');
        $oParser->addElementObject($oHrefElement);
        unset($oHrefElement);

        $oVideoElement = new ElementObject();
        $oVideoElement->voidElementOrStartTagOnly = true;
        $oVideoElement->setNamesArray(['video']);
        $oVideoElement->addPosAttrCriteriaRegex('(?:src|poster)');
        $oParser->addElementObject($oVideoElement);
        unset($oVideoElement);

        $oMediaElement = new ElementObject();
        $oMediaElement->voidElementOrStartTagOnly = true;
        $oMediaElement->setNamesArray(['meta']);
        $oMediaElement->addPosAttrCriteriaRegex('content');
        $oParser->addElementObject($oMediaElement);
        unset($oMediaElement);
    }

    public function getFullHtml(): string
    {
        return $this->html . $this->regexMarker;
    }

    public function setFullHtml(string $sHtml): void
    {
        $this->html = $this->cleanRegexMarker($sHtml);
    }

    protected function setUpCdnUrlCriteria(Parser $oParser): void
    {
        $oElements = new ElementObject();
        $oElements->voidElementOrStartTagOnly = true;
        $oElements->addPosAttrCriteriaRegex('style*=' . CssParser::cssUrlToken());
        $oParser->addElementObject($oElements);
        unset($oElements);

        $oStyleElement = new ElementObject();
        $oStyleElement->setNamesArray(['style']);
        $oStyleElement->addPosContentCriteriaRegex(CssParser::cssUrlToken());
        $oParser->addElementObject($oStyleElement);
        unset($oStyleElement);
    }

    public function processModulesForPreload(): array
    {
        try {
            $parser = new Parser();
            $parser->addExcludes(['noscript']);

            $element = new ElementObject();
            $element->setNamesArray(['script']);
            $element->addPosAttrCriteriaRegex('type==module');
            $element->addPosAttrCriteriaRegex('src');

            $parser->addElementObject($element);

            return $parser->findMatches($this->getFullHtml(), PREG_SET_ORDER);
        } catch (PregErrorException $e) {
            $this->logger?->error('ProcessModulesForPreload failed ' . $e->getMessage());
        }

        return [];
    }

    public function processDataFromCacheScriptToken(string $token): void
    {
        try {
            $parser = new Parser();
            $element = new ElementObject();
            $element->setNamesArray(['script']);
            $element->addPosAttrCriteriaRegex('type==application/(?:ld\+)?json');
            $element->addPosAttrCriteriaRegex('class~=joomla-script-options');
            $parser->addElementObject($element);

            $headHtml = $this->getHeadHtml();

            $matches = $parser->findMatches($headHtml);

            if (!empty($matches[0])) {
                $tokenized = preg_replace('#"csrf.token":"\K[^"]++#', $token, $matches[0]);
                $newHeadHtml = str_replace($matches[0], $tokenized, $headHtml);
                $this->setHeadHtml($newHeadHtml);
            }
        } catch (PregErrorException $e) {
            $this->logger?->error('ProcessDataFromCache failed ' . $e->getMessage());
        }
    }

    public function processAutoLcp(): void
    {
        try {
            $parser = new Parser();
            $element = new ElementObject();
            $element->setNamesArray(['link']);
            $element->voidElementOrStartTagOnly = true;
            $element->addPosAttrCriteriaRegex('class==jchoptimize-auto-lcp');
            $parser->addElementObject($element);

            $headHtml = $this->getHeadHtml();
            $cleanedHtml = $parser->removeMatches($headHtml);
            $this->setHeadHtml($cleanedHtml);
        } catch (PregErrorException $e) {
            $this->logger?->error('ProcessAutoLcp failed ' . $e->getMessage());
        }
    }

    public function removeScriptsFromHtml(string $html): string
    {
        try {
            $parser = new Parser();
            $element = new ElementObject();
            $element->setNamesArray(['script', 'style']);
            $parser->addElementObject($element);

            return $parser->removeMatches($html);
        } catch (PregErrorException $e) {
            $this->logger?->error('RemoveScriptsFromHtml failed ' . $e->getMessage());
            return $html;
        }
    }

    public function processJavaScriptForConfigureHelper(): array
    {
        $dynamicScripts = [];

        try {
            $parser = new Parser();
            $scriptElement = new ElementObject();
            $scriptElement->setNamesArray(['script']);
            $scriptElement->addNegAttrCriteriaRegex(
                "nomodule||type!=(?:module|(?:text|application)/javascript)"
            );
            $parser->addElementObject($scriptElement);
            $configureHelperCallback = $this->getContainer()->get(JavaScriptConfigureHelper::class);
            if ($configureHelperCallback instanceof JavaScriptConfigureHelper) {
                $configureHelperCallback->setSection('head');
                $parser->processMatchesWithCallback($this->getHeadHtml(), $configureHelperCallback);
                $configureHelperCallback->setSection('body');
                $parser->processMatchesWithCallback($this->getBodyHtml(), $configureHelperCallback);
                $dynamicScripts = $configureHelperCallback->getScripts();
            }
        } catch (PregErrorException $e) {
            $this->logger?->error('ProcessJavaScriptForConfigureHelper failed ' . $e->getMessage());
        }

        return $dynamicScripts;
    }

    public function getAboveFoldHtml(string $html): string
    {
        $aboveFoldHtml = '';
        $ex = Parser::htmlElementsToken(['script'])
            . '|' . Parser::htmlElementsToken(['style'])
            . '|' . Parser::htmlCommentToken();
        $regex = "#(?>{$ex}|</|[^<]++)*+(?:(?:<[0-9a-z!]++[^>]*+>[^<]*+(?><[^0-9a-z!][^<]*+)*+)|$)#six";
        preg_replace_callback(
            $regex,
            /** @var string[] $m */
            function (array $m) use (&$aboveFoldHtml): string {
                $aboveFoldHtml .= $m[0];

                return $m[0];
            },
            $html,
            (int)$this->params->get('elements_above_fold', 300)
        );

        return $aboveFoldHtml;
    }
}
