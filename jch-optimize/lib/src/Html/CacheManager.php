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

use _JchOptimizeVendor\GuzzleHttp\Psr7\UriResolver;
use _JchOptimizeVendor\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\Laminas\Cache\Exception\ExceptionInterface;
use _JchOptimizeVendor\Laminas\Cache\Pattern\CallbackCache;
use _JchOptimizeVendor\Laminas\Cache\Storage\IterableInterface;
use _JchOptimizeVendor\Laminas\Cache\Storage\StorageInterface;
use _JchOptimizeVendor\Laminas\Cache\Storage\TaggableInterface;
use _JchOptimizeVendor\Psr\Http\Message\UriInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareTrait;
use JchOptimize\Core\Combiner;
use JchOptimize\Core\Container\ContainerAwareTrait;
use JchOptimize\Core\Css\Processor as CssProcessor;
use JchOptimize\Core\Exception as CoreException;
use JchOptimize\Core\FeatureHelpers\DynamicJs;
use JchOptimize\Core\FeatureHelpers\Fonts;
use JchOptimize\Core\FeatureHelpers\LazyLoadExtended;
use JchOptimize\Core\FeatureHelpers\ResponsiveImages;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Html\Elements\Img;
use JchOptimize\Core\Http2Preload;
use JchOptimize\Core\PageCache\PageCache;
use JchOptimize\Core\Registry;
use JchOptimize\Core\SerializableTrait;
use JchOptimize\Core\StorageTaggingTrait;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\UriComparator;
use JchOptimize\Core\Uri\UriConverter;
use JchOptimize\Core\Uri\Utils;
use JchOptimize\Platform\Paths;
use JchOptimize\Platform\Profiler;

use function getimagesize;

\defined('_JCH_EXEC') or exit('Restricted access');

/**
 * Class CacheManager.
 */
class CacheManager implements LoggerAwareInterface, ContainerAwareInterface, \Serializable
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;
    use SerializableTrait;
    use StorageTaggingTrait;

    private Registry $params;

    /**
     * @var HtmlManager
     */
    private \JchOptimize\Core\Html\HtmlManager $htmlManager;

    /**
     * @var FilesManager
     */
    private \JchOptimize\Core\Html\FilesManager $filesManager;

    private CallbackCache $callbackCache;

    private Combiner $combiner;

    private Http2Preload $http2Preload;

    /**
     * @var Processor
     */
    private \JchOptimize\Core\Html\Processor $processor;

    /**
     * @var IterableInterface&StorageInterface&TaggableInterface
     */
    private $taggableCache;

    /**
     * @param IterableInterface&StorageInterface&TaggableInterface $taggableCache
     */
    public function __construct(Registry $params, HtmlManager $htmlManager, Combiner $combiner, FilesManager $filesManager, CallbackCache $callbackCache, $taggableCache, Http2Preload $http2Preload, Processor $processor)
    {
        $this->params = $params;
        $this->htmlManager = $htmlManager;
        $this->combiner = $combiner;
        $this->filesManager = $filesManager;
        $this->callbackCache = $callbackCache;
        $this->taggableCache = $taggableCache;
        $this->http2Preload = $http2Preload;
        $this->processor = $processor;
    }

    public function handleCombineJsCss(): void
    {
        // If amp page we don't generate combined files
        if ($this->processor->isAmpPage) {
            return;
        }
        // Indexed multidimensional array of files to be combined
        $aCssLinksArray = $this->filesManager->aCss;
        $aJsLinksArray = $this->filesManager->aJs;
        $section = '1' == $this->params->get('bottom_js', '0') ? 'body' : 'head';
        if (!Helper::isMsieLT10() && $this->params->get('combine_files_enable', '1')) {
            $bCombineCss = (bool) $this->params->get('css', 1);
            $bCombineJs = (bool) $this->params->get('js', 1);
            if ($bCombineCss && !empty($aCssLinksArray[0])) {
                /** @var CssProcessor $oCssProcessor */
                $oCssProcessor = $this->container->get(CssProcessor::class);
                $pageCss = '';
                $cssUrls = [];
                foreach ($aCssLinksArray as $cssLinksKey => $aCssLinks) {
                    // Optimize and cache css files
                    $aCssCache = $this->getCombinedFiles($aCssLinks, $sCssCacheId, 'css');
                    if (JCH_PRO) {
                        // @see Fonts::generateCombinedFilesForFonts()
                        $this->container->get(Fonts::class)->generateCombinedFilesForFonts($aCssCache);

                        /** @var LazyLoadExtended $lazyLoadExtended */
                        $lazyLoadExtended = $this->container->get(LazyLoadExtended::class);
                        $lazyLoadExtended->cssBgImagesSelectors = \array_merge($lazyLoadExtended->cssBgImagesSelectors, $aCssCache['bgselectors']);
                        foreach ($aCssCache['lcpImages'] as $lcpImage) {
                            $attributes = [];
                            if ($lcpImage['srcset']) {
                                $attributes = ['imagesrcset' => $lcpImage['srcset'], 'imagesizes' => ResponsiveImages::$sizes];
                            }
                            $this->http2Preload->preload($lcpImage['src'], 'image', '', 'high', $attributes);
                        }
                    }
                    // If Optimize CSS Delivery feature not enabled then we'll need to insert the link to
                    // the combined css file in the HTML
                    if (!$this->params->get('optimizeCssDelivery_enable', '0')) {
                        $this->htmlManager->replaceLinks($sCssCacheId, 'css', $section, $cssLinksKey);
                    } else {
                        $pageCss .= $aCssCache['contents'];
                        $cssUrls[] = $this->htmlManager->buildUrl($sCssCacheId, 'css');
                        $this->htmlManager->removeCSSLinks($cssLinksKey);
                    }
                }
                if ($this->params->get('optimizeCssDelivery_enable', '0')) {
                    try {
                        $sCriticalCss = $this->getCriticalCss($oCssProcessor, $pageCss, $id);
                        // Http2Preload push fonts and imaged in critical css
                        $oCssProcessor->preloadHttp2($sCriticalCss);
                        $this->htmlManager->addCriticalCssToHead($sCriticalCss, $id);
                        $this->htmlManager->loadCssAsync($cssUrls);
                    } catch (CoreException\ExceptionInterface $oException) {
                        $this->logger->warning('Optimize CSS Delivery failed: '.$oException->getMessage());
                    }
                }
            }
            if ($bCombineJs) {
                // If combine files successfully completed, proceed to place excluded files at bottom of section
                $this->htmlManager->addExcludedJsToSection($section);
                foreach ($aJsLinksArray as $aJsLinksKey => $aJsLinks) {
                    // Dynamically load files after the last excluded files if param is enabled
                    if ($this->params->get('pro_reduce_unused_js_enable', '0') && $this->htmlManager->noMoreExcludedJsFiles($aJsLinksKey)) {
                        DynamicJs::$dynamicJs[] = $aJsLinks;
                        $this->htmlManager->removeJsLinks($aJsLinksKey);

                        continue;
                    }
                    if (!empty($aJsLinks)) {
                        // Optimize and cache javascript files
                        $this->getCombinedFiles($aJsLinks, $sJsCacheId, 'js');
                        // Insert link to combined javascript file in HTML
                        $this->htmlManager->replaceLinks($sJsCacheId, 'js', $section, $aJsLinksKey);
                    }
                }
                // We also now append any deferred javascript files below the
                // last combined javascript file
                $this->htmlManager->addDeferredJs($section);
            }
        }
        if ($this->params->get('lazyload_enable', '0') && JCH_PRO && ($this->params->get('pro_lazyload_bgimages', '0') || $this->params->get('pro_lazyload_audiovideo', '0'))) {
            $jsLazyLoadAssets = $this->getJsLazyLoadAssets();
            $this->getCombinedFiles($jsLazyLoadAssets, $lazyLoadCacheId, 'js');
            $this->htmlManager->addJsLazyLoadAssetsToHtml($lazyLoadCacheId, $section);
        }
        $this->htmlManager->appendAsyncScriptsToHead();
    }

    /**
     * Returns contents of the combined files from cache.
     *
     * @param array       $links Indexed multidimensional array of file urls to combine
     * @param null|string $id    Id of generated cache file
     * @param string      $type  css or js
     *
     * @return null|mixed
     */
    public function getCombinedFiles(array $links, ?string &$id, string $type): mixed
    {
        !JCH_DEBUG ?: Profiler::start('GetCombinedFiles - '.$type);
        $aArgs = [$links];

        /**
         * @see Combiner::getCssContents()
         * @see Combiner::getJsContents()
         */
        $aFunction = [$this->combiner, 'get'.\ucfirst($type).'Contents'];
        $aCachedContents = $this->loadCache($aFunction, $aArgs, $id);
        !JCH_DEBUG ?: Profiler::stop('GetCombinedFiles - '.$type, \true);

        return $aCachedContents;
    }

    /**
     * @param array $ids         Ids of files that are already combined
     * @param array $fileMatches Array matches of file to be appended to the combined file
     *
     * @return null|mixed
     *
     * @throws ExceptionInterface
     */
    public function getAppendedFiles(array $ids, array $fileMatches, ?string &$id): mixed
    {
        !JCH_DEBUG ?: Profiler::start('GetAppendedFiles');
        $args = [$ids, $fileMatches, 'js'];
        $function = [$this->combiner, 'appendFiles'];
        $cachedContents = $this->loadCache($function, $args, $id);
        !JCH_DEBUG ?: Profiler::stop('GetAppendedFiles', \true);

        return $cachedContents;
    }

    public function handleImgAttributes(): void
    {
        if (!empty($this->processor->images)) {
            !JCH_DEBUG ?: Profiler::start('AddImgAttributes');

            try {
                $aImgAttributes = $this->loadCache([$this, 'getCachedImgAttributes'], [$this->processor->images], $id);
            } catch (CoreException\ExceptionInterface $e) {
                return;
            }
            $this->htmlManager->setImgAttributes($aImgAttributes);
        }
        !JCH_DEBUG ?: Profiler::stop('AddImgAttributes', \true);
    }

    public function getCachedImgAttributes(array $aImages): array
    {
        $aImgAttributes = [];
        foreach ($aImages[0] as $imgHtml) {
            try {
                /** @var Img $imgObj */
                $imgObj = \JchOptimize\Core\Html\HtmlElementBuilder::load($imgHtml);
            } catch (CoreException\PregErrorException $e) {
                $aImgAttributes[] = $imgHtml;

                continue;
            }
            if ($imgObj->hasAttribute('src')) {
                $uri = $imgObj->getSrc();
            } else {
                $uri = Utils::uriFor($imgObj->attributeValue('data-src'));
            }
            if (!$uri instanceof UriInterface) {
                $aImgAttributes[] = $imgHtml;

                continue;
            }
            $uri = UriResolver::resolve(SystemUri::currentUri(), $uri);
            if (UriComparator::isCrossOrigin($uri)) {
                $aImgAttributes[] = $imgHtml;

                continue;
            }
            $path = UriConverter::uriToFilePath($uri);
            if (!\file_exists($path)) {
                $aImgAttributes[] = $imgHtml;

                continue;
            }
            $aSize = @\getimagesize(\htmlentities($path));
            if (empty($aSize) || '1' == $aSize[0] && '1' == $aSize[1]) {
                $aImgAttributes[] = $imgHtml;

                continue;
            }
            $isImageAttrEnabled = $this->params->get('img_attributes_enable', '0');
            // Let's start with the assumption there are no attributes
            $existingAttributes = \false;
            // Checks for any existing width attribute
            if (($width = $imgObj->getWidth()) !== \false) {
                // Calculate height based on aspect ratio
                $iWidthAttrValue = \preg_replace('#[^0-9]#', '', $width, -1, $count);
                // Check if a value was found for the attribute
                if ($iWidthAttrValue && 0 == $count) {
                    // Value found so we try to add the height attribute
                    $height = (string) \round($aSize[1] / $aSize[0] * (int) $iWidthAttrValue);
                    // If add attributes not enabled put data-height instead
                    $isImageAttrEnabled ? $imgObj->height($height) : $imgObj->data('height', $height);
                    // Add height attribute to the img element and save in array
                    $aImgAttributes[] = $imgObj->render();
                    // We found an attribute
                    $existingAttributes = \true;
                } else {
                    // No value found, so we remove the attribute and add it later
                    $imgObj->remove('width');
                }
                // Check for any existing height attribute
            } elseif (($height = $imgObj->getHeight()) !== \false) {
                // Calculate width based on aspect ratio
                $iHeightAttrValue = \preg_replace('#[^0-9]#', '', $height, -1, $count);
                // Check if a value was found for the height
                if ($iHeightAttrValue && 0 == $count) {
                    $width = (string) \round($aSize[0] / $aSize[1] * (int) $iHeightAttrValue);
                    // if add attributes not enabled put data-width instead
                    $isImageAttrEnabled ? $imgObj->width($width) : $imgObj->data('width', $width);
                    // Add width attribute to the img element and save in array
                    $aImgAttributes[] = $imgObj->render();
                    $existingAttributes = \true;
                } else {
                    // No value found, we remove the attribute and add it later
                    $imgObj->remove('height');
                }
            }
            // No existing attributes, just go ahead and add attributes from getimagesize
            if (!$existingAttributes) {
                if ($isImageAttrEnabled) {
                    $imgObj->width((string) $aSize[0]);
                    $imgObj->height((string) $aSize[1]);
                } else {
                    $imgObj->data('width', $aSize[0]);
                    $imgObj->data('height', $aSize[1]);
                }
                $aImgAttributes[] = $imgObj->render();
            }
        }

        return $aImgAttributes;
    }

    /**
     * @throws CoreException\MissingDependencyException
     */
    protected function getCriticalCss(CssProcessor $oCssProcessor, string $pageCss, ?string &$iCacheId)
    {
        if (!\class_exists('DOMDocument') || !\class_exists('DOMXPath')) {
            throw new CoreException\MissingDependencyException('Document Object Model not supported');
        }
        $html = $this->processor->cleanHtml();
        // Remove all attributes from HTML elements to avoid randomly generated characters from creating excess cache
        $html = \preg_replace('#<([a-z0-9]++)[^>]*+>#i', '<\\1>', $html);
        // Truncate HTML to 400 elements to key cache
        $htmlKey = '';
        \preg_replace_callback('#<[a-z0-9]++[^>]*+>(?><?[^<]*+(<ul\\b[^>]*+>(?>[^<]*+<(?!ul)[^<]*+|(?1))*?</ul>)?)*?(?=<[a-z0-9])#i', function ($aM) use (&$htmlKey) {
            $htmlKey .= $aM[0];

            return $aM[0];
        }, $html, 400);
        $aArgs = [$pageCss, $htmlKey];

        /** @see CssProcessor::optimizeCssDelivery() */
        $aFunction = [$oCssProcessor, 'optimizeCssDelivery'];

        return $this->loadCache($aFunction, $aArgs, $iCacheId);
    }

    /**
     * Create and cache aggregated file if it doesn't exist and also tag the cache with the current page url.
     *
     * @param callable    $function Name of function used to aggregate filesG
     * @param array       $args     Arguments used by function above
     * @param null|string $id       Generated id to identify cached file
     *
     * @return null|mixed
     *
     * @throw CoreException\RuntimeException
     */
    private function loadCache(callable $function, array $args, ?string &$id): mixed
    {
        try {
            $id = $this->callbackCache->generateKey($function, $args);
            $results = $this->callbackCache->call($function, $args);
            $this->tagStorage($id);
            // if Tagging wasn't successful, best we abort
            if (empty($this->taggableCache->getTags($id))) {
                /** @var PageCache $pageCache */
                $pageCache = $this->container->get(PageCache::class);
                $pageCache->disableCaching();

                throw new \Exception('Tagging failed');
            }
            // Returns the contents of the combined file or false if failure
            return $results;
        } catch (\Exception|ExceptionInterface $e) {
            throw new CoreException\RuntimeException('Error creating cache files: '.$e->getMessage());
        }
    }

    private function getJsLazyLoadAssets(): array
    {
        $assets = [];
        $assets[]['url'] = Utils::uriFor(Paths::mediaUrl().'/core/js/ls.loader.js?'.JCH_VERSION);
        /*if (JCH_PRO && $this->params->get('pro_lazyload_effects', '0')) {
              $assets[]['url'] = Utils::uriFor(Paths::mediaUrl() . '/core/js/ls.loader.effects.js?' . JCH_VERSION);
          } */
        $assets[]['url'] = Utils::uriFor(Paths::mediaUrl().'/lazysizes/ls.unveilhooks.min.js?'.JCH_VERSION);
        $assets[]['url'] = Utils::uriFor(Paths::mediaUrl().'/lazysizes/lazysizes.min.js?'.JCH_VERSION);

        return $assets;
    }
}
