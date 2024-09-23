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

use Exception;
use GuzzleHttp\Psr7\UriResolver;
use JchOptimize\Core\Combiner;
use JchOptimize\Core\Css\Processor as CssProcessor;
use JchOptimize\Core\Exception as CoreException;
use JchOptimize\Core\FeatureHelpers\DynamicJs;
use JchOptimize\Core\FeatureHelpers\Fonts;
use JchOptimize\Core\FeatureHelpers\LazyLoadExtended;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Http2Preload;
use JchOptimize\Core\PageCache\PageCache;
use JchOptimize\Core\SerializableTrait;
use JchOptimize\Core\StorageTaggingTrait;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\UriComparator;
use JchOptimize\Core\Uri\UriConverter;
use JchOptimize\Core\Uri\Utils;
use JchOptimize\Platform\Paths;
use JchOptimize\Platform\Profiler;
use Joomla\DI\ContainerAwareInterface;
use Joomla\DI\ContainerAwareTrait;
use Joomla\Registry\Registry;
use Laminas\Cache\Pattern\CallbackCache;
use Laminas\Cache\Storage\IterableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Cache\Storage\TaggableInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Serializable;

use function class_exists;
use function count;
use function defined;
use function file_exists;
use function getimagesize;
use function htmlentities;
use function preg_replace;
use function preg_replace_callback;
use function round;
use function str_replace;
use function ucfirst;

defined('_JCH_EXEC') or die('Restricted access');

/**
 * Class CacheManager
 * @package JchOptimize\Core\Html
 *
 *          Handles the retrieval of contents from cache and hands over the repairing of the HTML to LinkBuilder
 */
class CacheManager implements LoggerAwareInterface, ContainerAwareInterface, Serializable
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;
    use SerializableTrait;
    use StorageTaggingTrait;

    /**
     * @var Registry
     */
    private Registry $params;

    /**
     * @var LinkBuilder
     */
    private LinkBuilder $linkBuilder;
    /**
     * @var FilesManager
     */
    private FilesManager $filesManager;

    /**
     * @var CallbackCache $callbackCache
     */
    private CallbackCache $callbackCache;

    /**
     * @var Combiner
     */
    private Combiner $combiner;
    /**
     * @var Http2Preload
     */
    private Http2Preload $http2Preload;
    /**
     * @var Processor
     */
    private Processor $processor;
    /**
     * @var StorageInterface&TaggableInterface&IterableInterface
     */
    private $taggableCache;

    /**
     * @param Registry $params
     * @param LinkBuilder $linkBuilder
     * @param Combiner $combiner
     * @param FilesManager $filesManager
     * @param CallbackCache $callbackCache
     * @param StorageInterface&TaggableInterface&IterableInterface $taggableCache
     * @param Http2Preload $http2Preload
     * @param Processor $processor
     */
    public function __construct(
        Registry      $params,
        LinkBuilder   $linkBuilder,
        Combiner      $combiner,
        FilesManager  $filesManager,
        CallbackCache $callbackCache,
        $taggableCache,
        Http2Preload  $http2Preload,
        Processor     $processor
    ) {
        $this->params = $params;
        $this->linkBuilder = $linkBuilder;
        $this->combiner = $combiner;
        $this->filesManager = $filesManager;
        $this->callbackCache = $callbackCache;
        $this->taggableCache = $taggableCache;
        $this->http2Preload = $http2Preload;
        $this->processor = $processor;
    }

    /**
     * @return void
     * @throws CoreException\ExceptionInterface
     *
     */
    public function handleCombineJsCss(): void
    {
        //If amp page we don't generate combined files
        if ($this->processor->isAmpPage) {
            return;
        }

        //Indexed multidimensional array of files to be combined
        $aCssLinksArray = $this->filesManager->aCss;
        $aJsLinksArray = $this->filesManager->aJs;

        $section = $this->params->get('bottom_js', '0') == '1' ? 'body' : 'head';

        if (!Helper::isMsieLT10() && $this->params->get('combine_files_enable', '1')) {
            $bCombineCss = (bool)$this->params->get('css', 1);
            $bCombineJs = (bool)$this->params->get('js', 1);

            if ($bCombineCss && !empty($aCssLinksArray[0])) {
                /** @var CssProcessor $oCssProcessor */
                $oCssProcessor = $this->container->get(CssProcessor::class);

                $pageCss = '';
                $cssUrls = [];

                foreach ($aCssLinksArray as $aCssLinks) {
                    //Optimize and cache css files
                    $aCssCache = $this->getCombinedFiles($aCssLinks, $sCssCacheId, 'css');

                    if (JCH_PRO) {
                        /** @see Fonts::generateCombinedFilesForFonts() */
                        $this->container->get(Fonts::class)->generateCombinedFilesForFonts($aCssCache);
                        /** @var LazyLoadExtended $lazyLoadExtended */
                        $lazyLoadExtended = $this->container->get(LazyLoadExtended::class);
                        $lazyLoadExtended->cssBgImagesSelectors = array_merge(
                            $lazyLoadExtended->cssBgImagesSelectors,
                            $aCssCache['bgselectors']
                        );
                    }

                    //If Optimize CSS Delivery feature not enabled then we'll need to insert the link to
                    //the combined css file in the HTML
                    if (!$this->params->get('optimizeCssDelivery_enable', '0')) {
                        //Http2Preload push
                        $oCssProcessor->preloadHttp2($aCssCache['contents'], true);
                        $this->linkBuilder->replaceLinks($sCssCacheId, 'css');
                    } else {
                        $pageCss .= $aCssCache['contents'];
                        $cssUrls[] = $this->linkBuilder->buildUrl($sCssCacheId, 'css');
                    }
                }

                if ($this->params->get('optimizeCssDelivery_enable', '0')) {
                    try {
                        $sCriticalCss = $this->getCriticalCss($oCssProcessor, $pageCss, $id);
                        //Http2Preload push fonts in critical css
                        $oCssProcessor->preloadHttp2($sCriticalCss);
                        $this->linkBuilder->addCriticalCssToHead($sCriticalCss, $id);
                        $this->linkBuilder->loadCssAsync($cssUrls);
                    } catch (CoreException\ExceptionInterface $oException) {
                        $this->logger->warning('Optimize CSS Delivery failed: ' . $oException->getMessage());

                        foreach ($cssUrls as $cssUrl) {
                            $this->linkBuilder->prependChildToHead($this->linkBuilder->getNewCssLink($cssUrl));
                        }
                    }
                }
            }

            if ($bCombineJs) {
                $this->linkBuilder->addExcludedJsToSection($section);

                if (!empty($aJsLinksArray[0])) {
                    foreach ($aJsLinksArray as $aJsLinksKey => $aJsLinks) {
                        //Dynamically load files after the last excluded files if param is enabled
                        if ($this->params->get('pro_reduce_unused_js_enable', '0')
                            && $aJsLinksKey >= $this->filesManager->jsExcludedIndex
                            && !empty($this->filesManager->aJs[$this->filesManager->iIndex_js])) {
                            DynamicJs::$dynamicJs[] = $aJsLinks;

                            continue;
                        }

                        //Optimize and cache javascript files
                        $this->getCombinedFiles($aJsLinks, $sJsCacheId, 'js');
                        //Insert link to combined javascript file in HTML
                        $this->linkBuilder->replaceLinks($sJsCacheId, 'js', $section, $aJsLinksKey);
                    }
                }

                //We also now append any deferred javascript files below the
                //last combined javascript file
                $this->linkBuilder->addDeferredJs($section);
            }
        }

        if ($this->params->get('lazyload_enable', '0')) {
            $jsLazyLoadAssets = $this->getJsLazyLoadAssets();
            $this->getCombinedFiles($jsLazyLoadAssets, $lazyLoadCacheId, 'js');
            $this->linkBuilder->addJsLazyLoadAssetsToHtml($lazyLoadCacheId, $section);
        }

        $this->linkBuilder->appendAsyncScriptsToHead();
    }

    /**
     * Returns contents of the combined files from cache
     *
     * @param array $links Indexed multidimensional array of file urls to combine
     * @param string|null $id Id of generated cache file
     * @param string $type css or js
     *
     * @return array|string Contents in array from cache containing combined file(s)
     */
    public function getCombinedFiles(array $links, ?string &$id, string $type)
    {
        !JCH_DEBUG ?: Profiler::start('GetCombinedFiles - ' . $type);

        $aArgs = [$links];

        /**
         * @see Combiner::getCssContents()
         * @see Combiner::getJsContents()
         */
        $aFunction = [$this->combiner, 'get' . ucfirst($type) . 'Contents'];

        $aCachedContents = $this->loadCache($aFunction, $aArgs, $id);

        !JCH_DEBUG ?: Profiler::stop('GetCombinedFiles - ' . $type, true);

        return $aCachedContents;
    }

    /**
     * Create and cache aggregated file if it doesn't exist and also tag the cache with the current page url
     *
     * @param callable $function Name of function used to aggregate filesG
     * @param array $args Arguments used by function above
     * @param string|null $id Generated id to identify cached file
     *
     * @return array|string|bool
     * @throws CoreException\RuntimeException
     */
    private function loadCache(callable $function, array $args, ?string &$id)
    {
        try {
            $id = $this->callbackCache->generateKey($function, $args);
            $results = $this->callbackCache->call($function, $args);
            $this->tagStorage($id);

            //if Tagging wasn't successful, best we abort
            if (empty($this->taggableCache->getTags($id))) {
                /** @var PageCache $pageCache */
                $pageCache = $this->container->get(PageCache::class);
                $pageCache->disableCaching();

                throw new Exception('Tagging failed');
            }
            //Returns the contents of the combined file or false if failure
            return $results;
        } catch (Exception $e) {
            throw new CoreException\RuntimeException('Error creating cache files: ' . $e->getMessage());
        }
    }

    /**
     * @return array|string|bool
     * @throws CoreException\MissingDependencyException
     */
    protected function getCriticalCss(CssProcessor $oCssProcessor, string $pageCss, ?string &$iCacheId)
    {
        if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
            throw new CoreException\MissingDependencyException('Document Object Model not supported');
        } else {
            $html = $this->processor->cleanHtml();
            //Remove all attributes from HTML elements to avoid randomly generated characters from creating excess cache
            $html = preg_replace('#<([a-z0-9]++)[^>]*+>#i', '<\1>', $html);

            //Truncate HTML to 400 elements to key cache
            $htmlKey = '';
            preg_replace_callback(
                '#<[a-z0-9]++[^>]*+>(?><?[^<]*+(<ul\b[^>]*+>(?>[^<]*+<(?!ul)[^<]*+|(?1))*?</ul>)?)*?(?=<[a-z0-9])#i',
                function ($aM) use (&$htmlKey) {
                    $htmlKey .= $aM[0];

                    return $aM[0];
                },
                $html,
                400
            );

            $aArgs = [$pageCss, $htmlKey];
            /** @see CssProcessor::optimizeCssDelivery() */
            $aFunction = [$oCssProcessor, 'optimizeCssDelivery'];

            return $this->loadCache($aFunction, $aArgs, $iCacheId);
        }
    }

    private function getJsLazyLoadAssets(): array
    {
        $assets = [];

        $assets[]['url'] = Utils::uriFor(Paths::mediaUrl() . '/core/js/ls.loader.js?' . JCH_VERSION);

        if (JCH_PRO && $this->params->get('pro_lazyload_effects', '0')) {
            $assets[]['url'] = Utils::uriFor(Paths::mediaUrl() . '/core/js/ls.loader.effects.js?' . JCH_VERSION);
        }

        if (JCH_PRO && ($this->params->get('pro_lazyload_bgimages', '0')
                || $this->params->get('pro_lazyload_audiovideo', '0'))) {
            $assets[]['url'] = Utils::uriFor(Paths::mediaUrl() . '/lazysizes/ls.unveilhooks.min.js?' . JCH_VERSION);
        }

        $assets[]['url'] = Utils::uriFor(Paths::mediaUrl() . '/lazysizes/lazysizes.min.js?' . JCH_VERSION);

        return $assets;
    }

    /**
     * @param array $ids Ids of files that are already combined
     * @param array $fileMatches Array matches of file to be appended to the combined file
     * @param string|null $id
     *
     * @return array|string|bool
     */
    public function getAppendedFiles(array $ids, array $fileMatches, ?string &$id)
    {
        !JCH_DEBUG ?: Profiler::start('GetAppendedFiles');

        $args = [$ids, $fileMatches, 'js'];
        $function = [$this->combiner, 'appendFiles'];

        $cachedContents = $this->loadCache($function, $args, $id);

        !JCH_DEBUG ?: Profiler::stop('GetAppendedFiles', true);

        return $cachedContents;
    }

    /**
     * @return void
     */
    public function handleImgAttributes(): void
    {
        if (!empty($this->processor->images)) {
            !JCH_DEBUG ?: Profiler::start('AddImgAttributes');

            try {
                $aImgAttributes = $this->loadCache([
                    $this,
                    'getCachedImgAttributes'
                ], [$this->processor->images], $id);
            } catch (CoreException\ExceptionInterface $e) {
                return;
            }

            $this->linkBuilder->setImgAttributes($aImgAttributes);
        }


        !JCH_DEBUG ?: Profiler::stop('AddImgAttributes', true);
    }

    /**
     *
     * @param array $aImages
     *
     * @return array
     */
    public function getCachedImgAttributes(array $aImages): array
    {
        $aImgAttributes = [];
        $total = count($aImages[0]);

        for ($i = 0; $i < $total; $i++) {
            if ($aImages[2][$i]) {
                //delimiter
                $delim = $aImages[3][$i];
                //Image url
                $url = $aImages[4][$i];
            } else {
                $delim = $aImages[6][$i];
                $url = $aImages[7][$i];
            }

            $uri = Utils::uriFor($url);
            $uri = UriResolver::resolve(SystemUri::currentUri(), $uri);

            if (UriComparator::isCrossOrigin($uri)) {
                $aImgAttributes[] = $aImages[0][$i];
                continue;
            }

            $path = UriConverter::uriToFilePath($uri);

            if (!file_exists($path)) {
                $aImgAttributes[] = $aImages[0][$i];
                continue;
            }

            $aSize = @getimagesize(htmlentities($path));

            if (empty($aSize) || ($aSize[0] == '1' && $aSize[1] == '1')) {
                $aImgAttributes[] = $aImages[0][$i];
                continue;
            }

            $isImageAttrEnabled = $this->params->get('img_attributes_enable', '0');
            //Let's start with the assumption there are no attributes
            $existingAttributes = false;

            //Checks for any existing width attribute
            if (FilesManager::hasAttributes($aImages[0][$i], ['width'], $aMatches)) {
                //Calculate height based on aspect ratio
                $iWidthAttrValue = preg_replace('#[^0-9]#', '', $aMatches[1]);

                //Check if a value was found for the attribute
                if ($iWidthAttrValue) {
                    //Value found so we try to add the height attribute
                    $height = round(($aSize[1] / $aSize[0]) * (int)$iWidthAttrValue, 2);
                    //If add attributes not enabled put data-height instead
                    $heightAttribute = $isImageAttrEnabled ? 'height=' : 'data-height=';
                    $heightAttribute .= $delim . $height . $delim;
                    //Add height attribute to the img element and save in array
                    $aImgAttributes[] = preg_replace('#\s*+/?>$#', ' ' . $heightAttribute . ' />', $aImages[0][$i]);

                    //We found an attribute
                    $existingAttributes = true;
                } else {
                    //No value found, so we remove the attribute and add it later
                    $aImages[0][$i] = str_replace($aMatches[0], '', $aImages[0][$i]);
                }
            } //Check for any existing height attribute
            elseif (FilesManager::hasAttributes($aImages[0][$i], ['height'], $aMatches)) {
                //Calculate width based on aspect ratio
                $iHeightAttrValue = preg_replace('#[^0-9]#', '', $aMatches[1]);

                //Check if a value was found for the height
                if ($iHeightAttrValue) {
                    $width = round(($aSize[0] / $aSize[1]) * (int)$iHeightAttrValue, 2);
                    //if add attributes not enabled put data-width instead
                    $widthAttribute = $isImageAttrEnabled ? 'width=' : 'data-width=';
                    $widthAttribute .= $delim . $width . $delim;
                    //Add width attribute to the img element and save in array
                    $aImgAttributes[] = preg_replace('#\s*+/?>$#', ' ' . $widthAttribute . ' />', $aImages[0][$i]);
                } else {
                    //No value found, we remove the attribute and add it later
                    $aImages[0][$i] = str_replace($aMatches[0], '', $aImages[0][$i]);
                }
            }

            //No existing attributes, just go ahead and add attributes from getimagesize
            if (!$existingAttributes) {
                //It's best to use the same delimiter for the width/height attributes that the urls used
                if ($delim) {
                    $sReplace = ' ' . str_replace('"', $delim, $aSize[3]);
                } //if there was no delimiter, we'll use whatever getimagesize() returns.
                else {
                    $sReplace = ' ' . $aSize[3];
                }

                //Add the width and height attributes from the getimagesize function
                $sReplace = preg_replace('#\s*+/?>$#', $sReplace . ' />', $aImages[0][$i]);

                if (!$isImageAttrEnabled) {
                    $sReplace = str_replace([
                        'width=',
                        'height='
                    ], ['data-width=', 'data-height='], $sReplace);
                }
                $aImgAttributes[] = $sReplace;
            }
        }

        return $aImgAttributes;
    }
}
