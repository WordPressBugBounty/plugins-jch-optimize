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

namespace JchOptimize\Core\FeatureHelpers;

use CodeAlfa\Minify\Css;
use JchOptimize\Core\Css\Callbacks\CorrectUrls;
use JchOptimize\Core\Css\Parser as CssParser;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Html\ElementObject;
use JchOptimize\Core\Html\LinkBuilder;
use JchOptimize\Core\Html\Parser;
use JchOptimize\Core\Html\Processor;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\Utils;
use JchOptimize\Platform\Paths;
use Joomla\DI\Container;
use Joomla\Registry\Registry;
use Laminas\EventManager\Event;

use function defined;
use function json_encode;
use function preg_match;
use function preg_replace;
use function str_replace;
use function strpos;

defined('_JCH_EXEC') or die('Restricted access');

class LazyLoadExtended extends AbstractFeatureHelper
{
    /**
     * @var array $cssBgImagesSelectors The selectors for CSS rules found with background images
     */
    public array $cssBgImagesSelectors = [];
    /**
     * @var LinkBuilder
     */
    private LinkBuilder $linkBuilder;

    public function __construct(Container $container, Registry $params, LinkBuilder $linkBuilder)
    {
        parent::__construct($container, $params);

        $this->linkBuilder = $linkBuilder;
    }

    public static function lazyLoadAudioVideo($matches, $return): string
    {
        //If poster value invalid just remove it
        if ($matches['posterAttribute'] !== false) {
            if ((string)$matches['posterValue'] == ''
                || $matches['posterValue']->getPath() == SystemUri::basePath()) {
                $return = str_replace($matches['posterAttribute'], '', $return);
            } else {
                $return = str_replace($matches['posterAttribute'], 'data-' . $matches['posterAttribute'], $return);
            }
        }

        if ($matches['preloadAttribute'] !== false) {
            $newPreloadAttribute = 'preload=' . $matches['preloadDelimiter'] . 'none' . $matches['preloadDelimiter'];
            $return = str_replace($matches['preloadAttribute'], $newPreloadAttribute, $return);
        } else {
            $return = str_replace(
                '<' . $matches['elementName'],
                '<' . $matches['elementName'] . ' preload="none"',
                $return
            );
        }

        if ($matches['autoplayAttribute'] !== false) {
            $return = str_replace($matches['autoplayAttribute'], 'data-' . $matches['autoplayAttribute'], $return);
        }

        return $return;
    }

    public static function lazyLoadBgImages($matches, $return): string
    {
        $newStyleAttribute = str_replace($matches['cssUrl'], '', $matches['styleAttribute']);

        if (strpos($matches['bgDeclaration'], 'background-image') !== false) {
            $newStyleAttribute = str_replace($matches['bgDeclaration'], 'background', $newStyleAttribute);
        }

        $newStyleAttribute = 'data-bg=' . $matches['styleDelimiter'] . $matches['cssUrlValue'] . $matches['styleDelimiter'] . ' ' . $newStyleAttribute;

        return str_replace($matches['styleAttribute'], $newStyleAttribute, $return);
    }

    public static function getLazyLoadClass($aMatches)
    {
        return $aMatches[4];
    }

    public function setupLazyLoadExtended(Parser $parser, $bDeferred): void
    {
        if ($bDeferred && $this->params->get('pro_lazyload_iframe', '0')) {
            $iFrameElement = new ElementObject();
            $iFrameElement->setNamesArray(['iframe']);
            $iFrameElement->setCaptureAttributesArray(['class', 'src']);
            $parser->addElementObject($iFrameElement);
            unset($iFrameElement);
        }

        if (!$bDeferred || $this->params->get('pro_lazyload_bgimages', '0')
            || $this->params->get('pro_next_gen_images', '1')
        ) {
            $bgElement = new ElementObject();
            $bgElement->setNamesArray(['[^\s/"\'=<>]++']);
            $bgElement->bSelfClosing = true;
            $bgElement->setCaptureAttributesArray(['class', 'style']);
            //language=RegExp
            $sValueCriteriaRegex = '(?=(?>[^b>]*+b?)*?[^b>]*+(background(?:-image)?))'
                                   . '(?=(?>[^u>]*+u?)*?[^u>]*+(' . CssParser::cssUrlWithCaptureValueToken(true) . '))';
            $bgElement->setValueCriteriaRegex(['style' => $sValueCriteriaRegex]);
            $parser->addElementObject($bgElement);
            unset($bgElement);

            $styleElement = new ElementObject();
            $styleElement->setNamesArray(['style']);
            $styleElement->addNegAttrCriteriaRegex('id==[\'"]?jch-optimize-critical-css[\'"]?');
            $styleElement->bCaptureContent = true;
            $parser->addElementObject($styleElement);
            unset($styleElement);
        }

        if ($bDeferred && $this->params->get('pro_lazyload_audiovideo', '0')) {
            $audioVideoElement = new ElementObject();
            $audioVideoElement->setNamesArray(['video', 'audio']);
            $audioVideoElement->setCaptureAttributesArray(['class', 'src', 'poster', 'preload', 'autoplay']);
            $parser->addElementObject($audioVideoElement);
            unset($audioVideoElement);
        }
    }

    public function lazyLoadCssBackgroundImages(Event $event): void
    {
        if ($this->params->get('lazyload_enable', '0') &&
            $this->params->get('pro_lazyload_bgimages', '0') &&
            !empty($this->cssBgImagesSelectors)
        ) {
            $cssSelectors = array_unique($this->cssBgImagesSelectors);
            $jsSelectorsArray = [];

            foreach ($cssSelectors as $cssSelector) {
                $jsSelectorsArray[] = [$cssSelector, Helper::cssSelectorsToClass($cssSelector)];
            }

            $jsSelectors = json_encode($jsSelectorsArray);

            $script = <<<HTML
<script>
document.addEventListener("DOMContentLoaded", (event) => {
    jchLazyLoadBgImages();
});
document.addEventListener("onJchDomLoaded", (event) => {
    jchLazyLoadBgImages();
});
function jchLazyLoadBgImages(){
    const selectors = {$jsSelectors};

    selectors.forEach(function(selectorPair){
        let elements = document.querySelectorAll(selectorPair[0])
    
        elements.forEach((element) => {
            if (element && !element.classList.contains(selectorPair[1])){
                element.classList.add(selectorPair[1],  'jch-lazyload');
            }
        });    
    });  
}
</script>
HTML;
            $this->linkBuilder->appendChildToHTML($script, 'body');
        }
    }

    public function addCssLazyLoadAssetsToHtml(Event $event): void
    {
        /** @var Processor $htmlProcessor */
        $htmlProcessor = $this->getContainer()->get(Processor::class);

        if (JCH_PRO && $this->params->get('lazyload_enable', '0') && !$htmlProcessor->isAmpPage) {
            if ($this->params->get('pro_lazyload_effects', '0')) {
                $url = Paths::mediaUrl() . '/core/css/ls.effects.css?' . JCH_VERSION;
                $link = $this->linkBuilder->getNewCssLink($url);
                $this->linkBuilder->appendChildToHead($link);
            }

            $cssNoScript = <<<HTML
<noscript>
    <style>
        img.jch-lazyload, iframe.jch-lazyload{
            display: none;
        }
    </style>
</noscript>
HTML;

            $this->linkBuilder->appendChildToHead($cssNoScript);
        }
    }

    public function handleCssBgImages(CorrectUrls $correctUrls, string $css): string
    {
        if (preg_match("#" . CssParser::cssRuleWithCaptureValueToken(true) . '#i', $css, $ruleMatches)) {
            //Make sure we're not lazyloading any URL that was commented out
            $cleanedCss = preg_replace('#' . CssParser::blockCommentToken() . '#', '', $ruleMatches[2]);
            if (preg_match(
                '#background(?:-image)?\s*+:\s*+\K' . CssParser::cssUrlWithCaptureValueToken(true) . '#',
                $cleanedCss,
                $urlMatches
            )) {
                $cssUri = Utils::uriFor($urlMatches[1]);

                //Don't need to lazy-load data-image
                if ($this->params->get('pro_lazyload_bgimages', '0')
                    && $cssUri->getScheme() != 'data'
                    //skip excluded images
                    && !Helper::findExcludes(
                        Helper::getArray($this->params->get('excludeLazyLoad', [])),
                        (string)$cssUri
                    )
                    && !Helper::findExcludes(
                        Helper::getArray($this->params->get('pro_excludeLazyLoadFolders', [])),
                        (string)$cssUri
                    )) {
                    //Remove the background image
                    $ruleMatches[0] = str_replace($urlMatches[0], '', $ruleMatches[0]);
                    //Remove any empty background declarations
                    $ruleMatches[0] = preg_replace(
                        '#background(?:-image)?\s*+:\s*+(?:!important)?\s*+(?:;|(?=}))#',
                        '',
                        $ruleMatches[0]
                    );
                    //Add the lazy-loaded image to CSS
                    $modifiedCss = $ruleMatches[0] . '.' . Helper::cssSelectorsToClass(
                        $ruleMatches[1]
                    ) . '.jch-lazyloaded{background-image:' . $urlMatches[0] . ' !important}';
                    //Save the selector for this rule
                    $correctUrls->cssBgImagesSelectors[] = Css::optimize($ruleMatches[1]);
                    $this->cssBgImagesSelectors[] = Css::optimize($ruleMatches[1]);

                    return $modifiedCss;
                }
            }
        }

        return $css;
    }
}
