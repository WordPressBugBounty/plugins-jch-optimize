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

use JchOptimize\Core\Cdn as CdnCore;
use JchOptimize\Core\Css\Parser as CssParser;
use JchOptimize\Core\Exception;
use JchOptimize\Core\FeatureHelpers\LazyLoadExtended;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Html\Callbacks\Cdn as CdnCallback;
use JchOptimize\Core\Html\Callbacks\CombineJsCss;
use JchOptimize\Core\Html\Callbacks\LazyLoad;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\Utils;
use JchOptimize\Platform\Profiler;
use Joomla\DI\ContainerAwareInterface;
use Joomla\DI\ContainerAwareTrait;
use Joomla\Registry\Registry;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

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

use const PREG_PATTERN_ORDER;
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
class Processor implements LoggerAwareInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;

    /**
     * @var bool           Indicates if the page is an Amp page
     */
    public bool $isAmpPage = false;
    /**
     * @var array $images Array of IMG elements requiring width/height attribute
     */
    public array $images = [];
    /**
     * @var Registry       Plugin parameters
     */
    private Registry $params;
    /**
     * @var string         Used to determine the end of useful string after parsing
     */
    private string $sRegexMarker = 'JCHREGEXMARKER';

    /**
     * @var string         HTML being processed
     */
    private string $html;

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
     * Returns the HTML being processed
     */
    public function getHtml(): string
    {
        return $this->html;
    }

    public function setHtml(string $html): void
    {
        $this->html = $html;
        //If amp page then combine CSS and JavaScript is disabled and any feature dependent of processing generated combined files,
        //and also lazy load images.
        $this->isAmpPage = (bool)preg_match('#<html [^>]*?(?:&\#26A1;|\bamp\b)#i', $html);
    }

    public function processCombineJsCss(): void
    {
        if ($this->params->get('combine_files_enable', '1')
            || $this->params->get('pro_http2_push_enable', '0')
            || $this->params->get('remove_css', [])
            || $this->params->get('remove_js', [])
        ) {
            try {
                $oParser = new Parser();
                $oParser->addExclude(Parser::htmlCommentToken());
                $oParser->addExclude(Parser::htmlElementToken('noscript'));
                $oParser->addExclude(Parser::htmlElementToken('template'));
                $oParser->addExclude(Parser::htmlElementToken('script'));
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
            } catch (Exception\ExceptionInterface $oException) {
                $this->logger->error('CombineJsCss failed ' . $oException->getMessage());
            }
        }
    }

    protected function setUpJsCssCriteria(Parser $oParser): void
    {
        $oJsFilesElement = new ElementObject();
        $oJsFilesElement->setNamesArray(['script']);
        //language=RegExp
        $oJsFilesElement->addNegAttrCriteriaRegex(
            'type==(?!(?>[\'"]?)(?:(?:text|application)/javascript|module)[\'"> ])'
        );
        $oJsFilesElement->setCaptureAttributesArray(['src']);
        $oJsFilesElement->setValueCriteriaRegex('(?=.)');
        $oParser->addElementObject($oJsFilesElement);

        $oJsContentElement = new ElementObject();
        $oJsContentElement->setNamesArray(['script']);
        //language=RegExp
        $oJsContentElement->addNegAttrCriteriaRegex(
            'src|type==(?!(?>[\'"]?)(?:(?:text|application)/javascript|module)[\'"> ])'
        );
        $oJsContentElement->bCaptureContent = true;
        $oParser->addElementObject($oJsContentElement);

        $oCssFileElement = new ElementObject();
        $oCssFileElement->bSelfClosing = true;
        $oCssFileElement->setNamesArray(['link']);
        //language=RegExp
        $oCssFileElement->addNegAttrCriteriaRegex(
            'itemprop|disabled|type==(?!(?>[\'"]?)text/css[\'"> ])|rel==(?!(?>[\'"]?)stylesheet[\'"> ])'
        );
        $oCssFileElement->setCaptureAttributesArray(['href']);
        $oCssFileElement->setValueCriteriaRegex('(?=.)');
        $oParser->addElementObject($oCssFileElement);

        $oStyleElement = new ElementObject();
        $oStyleElement->setNamesArray(['style']);
        //language=RegExp
        $oStyleElement->addNegAttrCriteriaRegex('scope|amp|type==(?!(?>[\'"]?)text/(?:css|stylesheet)[\'"> ])');
        $oStyleElement->bCaptureContent = true;
        $oParser->addElementObject($oStyleElement);
    }

    public function getHeadHtml(): string
    {
        preg_match('#' . Parser::htmlHeadElementToken() . '#i', $this->html, $aMatches);

        return $aMatches[0] . $this->sRegexMarker;
    }

    public function setHeadHtml(string $sHtml): void
    {
        $sHtml = $this->cleanRegexMarker($sHtml);
        $this->html = preg_replace(
            '#' . Parser::htmlHeadElementToken() . '#i',
            Helper::cleanReplacement($sHtml),
            $this->html,
            1
        );
    }

    protected function cleanRegexMarker(string $sHtml): ?string
    {
        return preg_replace('#' . preg_quote($this->sRegexMarker, '#') . '.*+$#', '', $sHtml);
    }

    public function getBodyHtml(): string
    {
        preg_match('#' . Parser::htmlBodyElementToken() . '#si', $this->html, $aMatches);

        return $aMatches[0] . $this->sRegexMarker;
    }

    public function setBodyHtml(string $sHtml): void
    {
        $sHtml = $this->cleanRegexMarker($sHtml);
        $this->html = preg_replace(
            '#' . Parser::htmlBodyElementToken() . '#si',
            Helper::cleanReplacement($sHtml),
            $this->html,
            1
        );
    }

    /**
     * @deprecated
     */
    public function isCombineFilesSet(): bool
    {
        return !Helper::isMsieLT10() && $this->params->get('combine_files_enable', '1');
    }

    /**
     * @return array
     */
    public function processImagesForApi(): array
    {
        try {
            $oParser = new Parser();
            $oParser->addExclude(Parser::htmlCommentToken());
            $oParser->addExclude(Parser::htmlElementsToken(['script', 'noscript', 'style']));

            $oImgElement = new ElementObject();
            $oImgElement->bSelfClosing = true;
            $oImgElement->setNamesArray(['img']);
            $oImgElement->setCaptureOneOrBothAttributesArray(['src', 'srcset']);
            $oParser->addElementObject($oImgElement);
            unset($oImgElement);

            $oBgElement = new ElementObject();
            $oBgElement->setNamesArray(['[^\s/"\'=<>]++']);
            $oBgElement->bSelfClosing = true;
            $oBgElement->setCaptureAttributesArray(['style']);
            //language=RegExp
            $sValueCriteriaRegex = '(?=(?>[^b>]*+b?)*?[^b>]*+(background(?:-image)?))'
                . '(?=(?>[^u>]*+u?)*?[^u>]*+(' . CssParser::cssUrlWithCaptureValueToken(true) . '))';
            $oBgElement->setValueCriteriaRegex(['style' => $sValueCriteriaRegex]);
            $oParser->addElementObject($oBgElement);
            unset($oBgElement);

            return $oParser->findMatches($this->getBodyHtml(), PREG_SET_ORDER);
        } catch (Exception\PregErrorException $oException) {
            $this->logger->error('ProcessApiImages failed ' . $oException->getMessage());
        }

        return [];
    }

    public function processLazyLoad(): void
    {
        $bLazyLoad = $this->params->get('lazyload_enable', '0') && !$this->isAmpPage;

        if (
            $bLazyLoad
            || $this->params->get('pro_http2_push_enable', '0')
            || $this->params->get('pro_load_webp_images', '0')
        ) {
            !JCH_DEBUG ?: Profiler::start('LazyLoadImages');

            $sHtml = $this->getBodyHtml();

            $sAboveFoldBody = '';
            preg_replace_callback(
                '#[^<]*+(?:<[0-9a-z!]++[^>]*+>[^<]*+(?><[^0-9a-z][^<]*+)*+)#six',
                function ($m) use (&$sAboveFoldBody) {
                    $sAboveFoldBody .= $m[0];

                    return $m[0];
                },
                $sHtml,
                (int)$this->params->get('lazyload_num_elements', 50)
            );

            $sBelowFoldHtml = substr($sHtml, strlen($sAboveFoldBody));
            $fullHtml = $this->getFullHtml();
            $aboveFoldHtml = substr($fullHtml, 0, strlen($fullHtml) - strlen($sBelowFoldHtml)) . $this->sRegexMarker;

            try {
                $http2Args = [
                    'lazyload' => false,
                    'deferred' => false,
                    'parent' => ''
                ];

                $oAboveFoldParser = new Parser();
                //language=RegExp
                $this->setupLazyLoadCriteria($oAboveFoldParser, false);
                /** @var LazyLoad $http2Callback */
                $http2Callback = $this->container->get(LazyLoad::class);
                $http2Callback->setLazyLoadArgs($http2Args);
                $processedAboveFoldHtml = $oAboveFoldParser->processMatchesWithCallback(
                    $aboveFoldHtml,
                    $http2Callback
                );


                $oBelowFoldParser = new Parser();
                $lazyLoadArgs = [
                    'lazyload' => $bLazyLoad,
                    'deferred' => true,
                    'parent' => '',
                ];

                $this->setupLazyLoadCriteria($oBelowFoldParser, true);
                /** @var LazyLoad $lazyLoadCallback */
                $lazyLoadCallback = $this->container->get(LazyLoad::class);
                $lazyLoadCallback->setLazyLoadArgs($lazyLoadArgs);
                $processedBelowFoldHtml = $oBelowFoldParser->processMatchesWithCallback(
                    $sBelowFoldHtml,
                    $lazyLoadCallback
                );

                $this->setFullHtml($this->cleanRegexMarker($processedAboveFoldHtml) . $processedBelowFoldHtml);
            } catch (Exception\PregErrorException $oException) {
                $this->logger->error('Lazy-load failed: ' . $oException->getMessage());
            }

            !JCH_DEBUG ?: Profiler::stop('LazyLoadImages', true);
        }
    }

    protected function setupLazyLoadCriteria(Parser $oParser, bool $bDeferred): void
    {
        $oParser->addExclude(Parser::htmlCommentToken());
        $oParser->addExclude(Parser::htmlElementToken('script'));
        $oParser->addExclude(Parser::htmlElementToken('noscript'));
        $oParser->addExclude(Parser::htmlElementToken('textarea'));
        $oParser->addExclude(Parser::htmlElementToken('template'));

        $oImgElement = new ElementObject();
        $oImgElement->bSelfClosing = true;
        $oImgElement->setNamesArray(['img']);
        //language=RegExp
        $oImgElement->addNegAttrCriteriaRegex('(?:data-(?:original-)?src)');
        $oImgElement->setCaptureAttributesArray([
            'class',
            'src',
            'srcset',
            '(?:data-)?width',
            '(?:data-)?height'
        ]);
        $oParser->addElementObject($oImgElement);
        unset($oImgElement);

        $oInputElement = new ElementObject();
        $oInputElement->bSelfClosing = true;
        $oInputElement->setNamesArray(['input']);
        //language=RegExp
        $oInputElement->addPosAttrCriteriaRegex('type=(?>[\'"]?)image[\'"> ]');
        $oInputElement->setCaptureAttributesArray(['class', 'src']);
        $oParser->addElementObject($oInputElement);
        unset($oInputElement);

        $oPictureElement = new ElementObject();
        $oPictureElement->setNamesArray(['picture']);
        $oPictureElement->setCaptureAttributesArray(['class']);
        $oPictureElement->bCaptureContent = true;
        $oParser->addElementObject($oPictureElement);
        unset($oPictureElement);

        if (JCH_PRO) {
            /** @see LazyLoadExtended::setupLazyLoadExtended() */
            $this->container->get(LazyLoadExtended::class)->setupLazyLoadExtended($oParser, $bDeferred);
        }
    }

    public function processImageAttributes(): void
    {
        if ($this->params->get('img_attributes_enable', '0') || ($this->params->get(
            'lazyload_enable',
            '0'
        ) && $this->params->get('lazyload_autosize', '0'))) {
            !JCH_DEBUG ?: Profiler::start('ProcessImageAttributes');

            $oParser = new Parser();
            $oParser->addExclude(Parser::htmlCommentToken());
            $oParser->addExclude(Parser::htmlElementToken('script'));
            $oParser->addExclude(Parser::htmlElementToken('noscript'));
            $oParser->addExclude(Parser::htmlElementToken('textarea'));
            $oParser->addExclude(Parser::htmlElementToken('template'));

            $oImgElement = new ElementObject();
            $oImgElement->setNamesArray(['img']);
            $oImgElement->bSelfClosing = true;
            //language=RegExp
            $oImgElement->addPosAttrCriteriaRegex('width');
            //language=RegExp
            $oImgElement->addPosAttrCriteriaRegex('height');
            $oImgElement->bNegateCriteria = true;
            $oImgElement->setCaptureAttributesArray(['data-src', 'src']);
            $oImgElement->addNegAttrCriteriaRegex('src-set');
            $oParser->addElementObject($oImgElement);

            try {
                $this->images = $oParser->findMatches($this->getBodyHtml());
            } catch (Exception\PregErrorException $oException) {
                $this->logger->error('Image Attributes matches failed: ' . $oException->getMessage());
            }

            !JCH_DEBUG ?: Profiler::stop('ProcessImageAttributes', true);
        }
    }

    /**
     * @return void
     */
    public function processCdn(): void
    {
        if (!$this->params->get('cookielessdomain_enable', '0') ||
            (trim($this->params->get('cookielessdomain', '')) == '' &&
                trim($this->params->get('pro_cookielessdomain_2', '')) == '' &&
                trim($this->params->get('pro_cookieless_3', '')) == '')
        ) {
            return;
        }

        !JCH_DEBUG ?: Profiler::start('RunCookieLessDomain');

        $cdnCore = $this->container->get(CdnCore::class);

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
        $oBaseElement->bSelfClosing = true;
        $oBaseElement->setCaptureAttributesArray(['href']);
        $oBaseParser->addElementObject($oBaseElement);

        $aMatches = $oBaseParser->findMatches($this->getHeadHtml());
        unset($oBaseParser);
        unset($oBaseElement);

        $baseUri = SystemUri::currentUri();

        //Adjust $dir if necessary based on <base/>
        if (!empty($aMatches[0])) {
            $uri = Utils::uriFor($aMatches[4][0]);

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
            //Get regex for <script> without src attribute
            $oElementParser = new Parser();

            $oElementWithCriteria = new ElementObject();
            $oElementWithCriteria->setNamesArray(['script']);
            $oElementWithCriteria->addNegAttrCriteriaRegex('src');

            $oElementParser->addElementObject($oElementWithCriteria);
            $sScriptWithoutSrc = $oElementParser->getElementWithCriteria();
            unset($oElementParser);
            unset($oElementWithCriteria);

            //Process cdn for elements with href or src attributes
            $oSrcHrefParser = new Parser();
            $oSrcHrefParser->addExclude(Parser::htmlCommentToken());
            $oSrcHrefParser->addExclude($sScriptWithoutSrc);

            $this->setUpCdnSrcHrefCriteria($oSrcHrefParser, $valueMatch);

            /** @var CdnCallback $cdnCallback */
            $cdnCallback = $this->container->get(CdnCallback::class);
            $cdnCallback->setBaseUri($baseUri);
            $cdnCallback->setLocalhost($host);
            $sCdnHtml = $oSrcHrefParser->processMatchesWithCallback($this->getFullHtml(), $cdnCallback);
            unset($oSrcHrefParser);

            $this->setFullHtml($sCdnHtml);

            //Process cdn for CSS urls in style attributes or <style/> elements
            //language=RegExp
            $sUrlSearchRegex = '(?=((?>[^()<>]*+[()]?)*?[^()<>]*+(?<=url)\((?>[\'"]?)' . $valueMatch . '))';

            $oUrlParser = new Parser();
            $oUrlParser->addExclude(Parser::htmlCommentToken());
            $oUrlParser->addExclude(Parser::htmlElementsToken(['script', 'link', 'meta']));
            $this->setUpCdnUrlCriteria($oUrlParser, $sUrlSearchRegex);
            $cdnCallback->setContext('cssurl');
            $cdnCallback->setSearchRegex($valueMatch);
            $sCdnUrlHtml = $oUrlParser->processMatchesWithCallback($this->getFullHtml(), $cdnCallback);
            unset($oUrlParser);

            $this->setFullHtml($sCdnUrlHtml);

            //Process cdn for elements with srcset attributes
            $oSrcsetParser = new Parser();
            $oSrcsetParser->addExclude(Parser::htmlCommentToken());
            $oSrcsetParser->addExclude(Parser::htmlElementToken('script'));
            $oSrcsetParser->addExclude(Parser::htmlElementToken('style'));

            $oSrcsetElement = new ElementObject();
            $oSrcsetElement->bSelfClosing = true;
            $oSrcsetElement->setNamesArray(['img', 'source']);
            $oSrcsetElement->setCaptureOneOrBothAttributesArray(['srcset', 'data-srcset']);
            $oSrcsetElement->setValueCriteriaRegex('(?=.)');

            $oSrcsetParser->addElementObject($oSrcsetElement);
            $cdnCallback->setContext('srcset');
            $sCdnSrcsetHtml = $oSrcsetParser->processMatchesWithCallback($this->getBodyHtml(), $cdnCallback);
            unset($oSrcsetParser);
            unset($oSrcsetElement);

            $this->setBodyHtml($sCdnSrcsetHtml);
        } catch (Exception\PregErrorException $oException) {
            $this->logger->error('Cdn failed :' . $oException->getMessage());
        }

        !JCH_DEBUG ?: Profiler::stop('RunCookieLessDomain', true);
    }

    protected function setUpCdnSrcHrefCriteria(Parser $oParser, string $sValueMatch): void
    {
        $oSrcElement = new ElementObject();
        $oSrcElement->bSelfClosing = true;
        $oSrcElement->setNamesArray(['img', 'script', 'source', 'input']);
        $oSrcElement->setCaptureOneOrBothAttributesArray(['src', 'data-src']);
        $oSrcElement->setValueCriteriaRegex($sValueMatch);
        $oParser->addElementObject($oSrcElement);
        unset($oSrcElement);

        $oHrefElement = new ElementObject();
        $oHrefElement->bSelfClosing = true;
        $oHrefElement->setNamesArray(['a', 'link', 'image']);
        $oHrefElement->setCaptureAttributesArray(['(?:xlink:)?href']);
        $oHrefElement->setValueCriteriaRegex($sValueMatch);
        $oParser->addElementObject($oHrefElement);
        unset($oHrefElement);

        $oVideoElement = new ElementObject();
        $oVideoElement->bSelfClosing = true;
        $oVideoElement->setNamesArray(['video']);
        $oVideoElement->setCaptureAttributesArray(['(?:src|poster)']);
        $oVideoElement->setValueCriteriaRegex($sValueMatch);
        $oParser->addElementObject($oVideoElement);
        unset($oVideoElement);

        $oMediaElement = new ElementObject();
        $oMediaElement->bSelfClosing = true;
        $oMediaElement->setNamesArray(['meta']);
        $oMediaElement->setCaptureAttributesArray(['content']);
        $oMediaElement->setValueCriteriaRegex($sValueMatch);
        $oParser->addElementObject($oMediaElement);
        unset($oMediaElement);
    }

    public function getFullHtml(): string
    {
        return $this->html . $this->sRegexMarker;
    }

    public function setFullHtml(string $sHtml): void
    {
        $this->html = $this->cleanRegexMarker($sHtml);
    }

    protected function setUpCdnUrlCriteria(Parser $oParser, string $sValueMatch): void
    {
        $oElements = new ElementObject();
        $oElements->bSelfClosing = true;
        //language=RegExp
        $oElements->setNamesArray(['(?!style|script|link|meta)[^\s/"\'=<>]++']);
        $oElements->setCaptureAttributesArray(['style']);
        $oElements->setValueCriteriaRegex($sValueMatch);
        $oParser->addElementObject($oElements);
        unset($oElements);

        $oStyleElement = new ElementObject();
        $oStyleElement->setNamesArray(['style']);
        $oStyleElement->bCaptureContent = true;
        $oStyleElement->setValueCriteriaRegex($sValueMatch);
        $oParser->addElementObject($oStyleElement);
        unset($oStyleElement);
    }

    public function processModulesForPreload(): array
    {
        try {
            $parser = new Parser();
            $parser->addExclude(Parser::htmlCommentToken());
            $parser->addExclude(Parser::htmlElementToken('noscript'));

            $element = new ElementObject();
            $element->setNamesArray(['script']);
            $element->addPosAttrCriteriaRegex('type==[\'"]?module');
            $element->setCaptureAttributesArray(['src']);
            $element->setValueCriteriaRegex('(?=.)');

            $parser->addElementObject($element);

            return $parser->findMatches($this->getFullHtml(), PREG_PATTERN_ORDER);
        } catch (Exception\PregErrorException $e) {
            $this->logger->error('ProcessModulesForPreload feiled ' . $e->getMessage());
        }

        return [];
    }

    public function processDataFromCacheScriptToken(string $token): void
    {
        try {
            $parser = new Parser();
            $element = new ElementObject();
            $element->setNamesArray(['script']);
            $element->addPosAttrCriteriaRegex('type==(?>[\'"]?)application/(?:ld\+)?json');
            $element->addPosAttrCriteriaRegex('class==(?>[\'"]?)[^\'"<>]*?joomla-script-options');
            $parser->addElementObject($element);

            $headHtml = $this->getHeadHtml();

            $matches = $parser->findMatches($this->getHeadHtml());

            if (!empty($matches[0])) {
                $tokenized = preg_replace('#"csrf.token":"\K[^"]++#', $token, $matches[0]);
                $newHeadHtml = str_replace($matches[0], $tokenized, $headHtml);
                $this->setHeadHtml($newHeadHtml);
            }
        } catch (Exception\PregErrorException $e) {
            $this->logger->error('ProcessDataFromCache failed ' . $e->getMessage());
        }
    }

    /**
     *
     * @return string
     */
    public function cleanHtml(): string
    {
        $aSearch = [
            '#' . Parser::htmlHeadElementToken() . '#ix',
            '#' . Parser::htmlCommentToken() . '#ix',
            '#' . Parser::htmlElementToken('script') . '#ix',
            '#' . Parser::htmlElementToken('style') . '#ix',
            '#' . Parser::htmlElementToken('link', true) . '#six',

        ];

        $aReplace = [
            '<head><title></title></head>',
            '',
            '',
            '',
            ''
        ];

        $html = preg_replace($aSearch, $aReplace, $this->html);

        //Remove any hidden element from HtmL
        $html = preg_replace_callback('#(<[^>]*+>)[^<>]*+#ix', function ($aMatches) {
            if (preg_match('#type\s*+=\s*+["\']?hidden["\'\s>]|\shidden(?=[\s>=])[^>\'"=]*+[>=]#i', $aMatches[1])) {
                return '';
            }

            //Add linebreak for readability during debugging
            return $aMatches[1] . "\n";
        }, $html);

        //Remove Text nodes
        //Remove text nodes from HTML elements
        return preg_replace_callback(
            '#(<(?>[^<>]++|(?1))*+>)|((?<=>)(?=[^<>\S]*+[^<>\s])[^<>]++)#',
            function ($m) {
                if (!empty($m[1])) {
                    return $m[0];
                }

                if (!empty($m[2])) {
                    return ' ';
                }

                return '';
            },
            $html
        );
    }
}
