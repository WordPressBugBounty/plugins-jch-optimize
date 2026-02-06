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
use _JchOptimizeVendor\V91\Laminas\Cache\Exception\ExceptionInterface as LaminasCacheExceptionInterface;
use _JchOptimizeVendor\V91\Laminas\Cache\Pattern\CallbackCache;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\IterableInterface;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\StorageInterface;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\TaggableInterface;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareTrait;
use Exception;
use JchOptimize\Core\CacheObject;
use JchOptimize\Core\Combiner;
use JchOptimize\Core\Css\Callbacks\Dependencies\CriticalCssDependencies;
use JchOptimize\Core\Css\Callbacks\Dependencies\CriticalCssDomainProfiler;
use JchOptimize\Core\Css\CssProcessor;
use JchOptimize\Core\Exception\ExceptionInterface;
use JchOptimize\Core\Exception\PregErrorException;
use JchOptimize\Core\Exception\RuntimeException;
use JchOptimize\Core\FeatureHelpers\Fonts;
use JchOptimize\Core\FeatureHelpers\LazyLoadExtended;
use JchOptimize\Core\FeatureHelpers\LCPImages;
use JchOptimize\Core\FileInfo;
use JchOptimize\Core\Html\CssLayout\CssLayoutPlanner;
use JchOptimize\Core\Html\Elements\Link;
use JchOptimize\Core\Html\Elements\Script;
use JchOptimize\Core\Html\JsLayout\JsLayoutPlanner;
use JchOptimize\Core\ImageAttributes;
use JchOptimize\Core\PageCache\PageCache;
use JchOptimize\Core\Platform\ProfilerInterface;
use JchOptimize\Core\Preloads\Http2Preload;
use JchOptimize\Core\Registry;
use JchOptimize\Core\SerializableTrait;
use Serializable;

use function array_key_last;
use function array_merge;
use function defined;
use function in_array;
use function is_array;
use function str_starts_with;
use function ucfirst;

defined('_JCH_EXEC') or die('Restricted access');

class CacheManager implements LoggerAwareInterface, ContainerAwareInterface, Serializable
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;
    use SerializableTrait;

    public function __construct(
        private Registry $params,
        private HtmlManager $htmlManager,
        private Combiner $combiner,
        private FilesManager $filesManager,
        private CallbackCache $callbackCache,
        /**
         * @var StorageInterface&TaggableInterface&IterableInterface
         */
        private $taggableCache,
        private Http2Preload $http2Preload,
        private HtmlProcessor $processor,
        private ImageAttributes $imageAttributes,
        private ProfilerInterface $profiler,
        private CssLayoutPlanner $cssLayoutPlanner,
        private JsLayoutPlanner $jsLayoutPlanner
    ) {
    }

    /**
     * @throws LaminasCacheExceptionInterface
     * @throws PregErrorException
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

        if ($this->params->get('combine_files_enable', '1')) {
            $bCombineCss = (bool)$this->params->get('css', 1);
            $bCombineJs = (bool)$this->params->get('javascript', 1);

            if ($bCombineCss && !empty($aCssLinksArray[0])) {
                $this->handleCss($aCssLinksArray);
            }

            if ($bCombineJs) {
                $this->handleJs($aJsLinksArray, $section);
            }
        }
    }

    /**
     * @throws LaminasCacheExceptionInterface
     * @throws PregErrorException
     */
    private function handleCss(array $aCssLinksArray): void
    {
        $cssCacheIds = [];
        $cssFileInfos = [];
        $combinedByGroup = [];
        $cacheObjByGroup = [];
        $sensitiveCacheObjByOrds = [];
        $belowFoldFontsEl = null;
        $reducedBundleEl = null;

        $optimizeDelivery = $this->params->get('optimizeCssDelivery_enable', '0');
        $reduceUnusedCss = $this->params->get('pro_reduce_unused_css', '0');

        $cssTimeLine = $this->filesManager->cssTimeLine;
        $layoutPlanner = $this->cssLayoutPlanner->plan($cssTimeLine, $optimizeDelivery, $reduceUnusedCss);

        if ($optimizeDelivery) {
            foreach ($cssTimeLine as $item) {
                if ($item->isSensitive) {
                    $fileInfo = new FileInfo(clone $item->node);
                    $cacheObj = $this->getCombinedFiles([$fileInfo], $id, 'css', false, $hit);
                    $this->updateOptimizeCssDelivery($cacheObj, $item->node, $hit, false);
                    $sensitiveCacheObjByOrds[$item->ordinal] = $cacheObj;
                }
            }
        }

        /**
         * @var  int $cssLinksKey
         * @var  FileInfo[] $cssInfosArray
         */
        foreach ($aCssLinksArray as $cssLinksKey => $cssInfosArray) {
            $isLastKey = array_key_last($aCssLinksArray) == $cssLinksKey;
            $this->combiner->setIsLastKey($isLastKey);

            $cssCacheObj = $this->getCombinedFiles($cssInfosArray, $cssCacheId, 'css', $isLastKey, $hit);
            $element = $this->getElementFromCacheId($cssCacheId, $cssInfosArray[0], $cssCacheObj);
            $combinedByGroup[$cssLinksKey] = $element;
            $cacheObjByGroup[$cssLinksKey] = $cssCacheObj;

            //If Optimize CSS Delivery feature not enabled then we'll need to insert the link to
            //the combined css file in the HTML
            if ($optimizeDelivery) {
                $this->updateOptimizeCssDelivery($cssCacheObj, $element, $hit, $isLastKey);
                $this->htmlManager->preloadStyleSheet($element, 'low');
                if (JCH_PRO && $reduceUnusedCss) {
                    $cssCacheIds[] = $cssCacheId;
                }

                if ($isLastKey && $cssCacheObj->getBelowFoldFontsKeyFrame() !== '') {
                    if (JCH_PRO && $reduceUnusedCss) {
                        $cssFileInfos[] = $this->getBelowFoldFontsFileInfo($cssCacheObj);
                    } else {
                        // Build special below-the-fold fonts <link> element,
                        // but do NOT inject it yet; pass it to applyCssPlan.
                        $belowFoldFontsEl = $this->getBelowFoldFontsKeyFrame($cssCacheObj);
                    }
                }
            }

            if (JCH_PRO) {
                $this->handleCssFeatureHelpers($cssCacheObj);
            }

            $this->handleHttp2Preloads($cssCacheObj);
        }

        // Reduced unused CSS bundle: build the appended file and the <link> element.
        if ($reduceUnusedCss && (!empty($cssCacheIds) || !empty($cssFileInfos))) {
            $appendedCacheObj = $this->getAppendedFiles($cssCacheIds, $cssFileInfos, $appendedCssId, 'css');

            $reducedBundleEl = $this->htmlManager
                ->getNewCssLink($this->htmlManager->buildUrl($appendedCssId, 'css', $appendedCacheObj))
                ->type('jchoptimize-text/css');
        }

        $this->htmlManager->applyCssPlan(
            $layoutPlanner,
            $combinedByGroup,
            $cacheObjByGroup,
            $sensitiveCacheObjByOrds,
            $belowFoldFontsEl,
            $reducedBundleEl
        );

        $cssProfiler = $this->getContainer()->get(CriticalCssDomainProfiler::class);
        $stats = $cssProfiler->snapshot();

        if ($stats) {
            $this->logger->debug(
                'Critical CSS profiling: ' . json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        }
    }

    private function handleJs(array $aJsLinksArray, string $section): void
    {
        $layoutPlan = $this->jsLayoutPlanner->plan($this->filesManager->jsTimeLine);
        $combinedByGroup = [];
        /**
         * @var int $aJsLinksKey
         * @var FileInfo[] $jsInfosArray
         */
        foreach ($aJsLinksArray as $aJsLinksKey => $jsInfosArray) {
            if (!empty($jsInfosArray)) {
                //Optimize and cache javascript files
                $jsCacheObj = $this->getCombinedFiles($jsInfosArray, $sJsCacheId, 'js');
                $combinedByGroup[$aJsLinksKey] = $this->getElementFromCacheId(
                    $sJsCacheId,
                    $jsInfosArray[0],
                    $jsCacheObj
                );
            }
        }

        $this->htmlManager->applyJsPlan($layoutPlan, $combinedByGroup, $section);
    }

    /**
     * @throws RuntimeException
     */
    public function getCombinedFiles(
        array $fileInfosArray,
        ?string &$id,
        string $type,
        bool $isLastKey = false,
        ?bool &$hit = null
    ): CacheObject {
        !JCH_DEBUG ?: $this->profiler->start('GetCombinedFiles - ' . $type);

        $aArgs = [$fileInfosArray, $isLastKey];

        /**
         * @see Combiner::getCssContents()
         * @see Combiner::getJsContents()
         */
        $aFunction = [$this->combiner, 'get' . ucfirst($type) . 'Contents'];

        $aCachedContents = $this->loadCache($aFunction, $aArgs, $id, $hit);

        !JCH_DEBUG ?: $this->profiler->stop('GetCombinedFiles - ' . $type, true);

        return $aCachedContents;
    }

    /**
     * Create and cache aggregated file if it doesn't exist and also tag the cache with the current page url
     *
     * @throws RuntimeException
     */
    private function loadCache(callable $function, array $args, ?string &$id, ?bool &$hit = null): mixed
    {
        try {
            $storage = $this->callbackCache->getStorage();
            $id = $this->callbackCache->generateKey($function, $args);
            $hit = $storage->hasItem($id);
            $results = $this->callbackCache->call($function, $args);
            $this->tagStorage($id);

            if ($hit) {
                $storage->touchItem($id);
            }

            return $results;
        } catch (Exception | LaminasCacheExceptionInterface $e) {
            throw new RuntimeException('Error creating cache files: ' . $e->getMessage());
        }
    }

    protected function getHtmlKey(): string
    {
        /** @var CriticalCssDependencies $deps */
        $deps = $this->getContainer()->get(CriticalCssDependencies::class);

        return $deps->getHtmlKey();
    }

    public function getAppendedFiles(array $ids, array $fileInfos, ?string &$id, string $type = 'js'): CacheObject
    {
        !JCH_DEBUG ?: $this->profiler->start('GetAppendedFiles');

        $args = [$ids, $fileInfos, $type];
        $function = [$this->combiner, 'appendFiles'];

        $cachedContents = $this->loadCache($function, $args, $id);

        !JCH_DEBUG ?: $this->profiler->stop('GetAppendedFiles', true);

        return $cachedContents;
    }

    /**
     * @throws PregErrorException
     */
    public function handleImgAttributes(): void
    {
        if (!empty($this->processor->images)) {
            !JCH_DEBUG ?: $this->profiler->start('AddImgAttributes');

            try {
                $aImgAttributes = $this->loadCache([
                    $this,
                    'getCachedImgAttributes'
                ], [$this->processor->images], $id);
            } catch (ExceptionInterface) {
                return;
            }

            if (!empty($aImgAttributes)) {
                $this->htmlManager->setImgAttributes($aImgAttributes);
            }
        }

        !JCH_DEBUG ?: $this->profiler->stop('AddImgAttributes', true);
    }

    public function getCachedImgAttributes(array $images): array
    {
        return $this->imageAttributes->getImageAttributes($images);
    }

    private function getElementFromCacheId(string $cacheId, FileInfo $fileInfo, CacheObject $cacheObj): Link|Script
    {
        $type = $fileInfo->getType();
        $uri = $this->htmlManager->buildUrl($cacheId, $type, $cacheObj);

        /** @see HtmlManager::getNewCssLink() */
        /** @see HtmlManager::getNewJsLink() */
        $node = $this->htmlManager->{'getNew' . ucfirst($type) . 'Link'}($uri);

        if (!$this->params->get('combine_files', '0')) {
            $el = $fileInfo->getElement();

            if ($el instanceof HtmlElementInterface) {
                foreach ($el->getAttributes() as $attr) {
                    $name = $attr->getName();
                    $value = $attr->getValue();

                    if (
                        in_array($name, ['id', 'class', 'nonce'])
                        || (str_starts_with('data-', $name))
                    ) {
                        $node->attribute($name, $value);
                    }
                }
            }
        }

        return $this->htmlManager->addDataFileToElement($node, $fileInfo);
    }

    private function handleHttp2Preloads(CacheObject $cssCacheObj): void
    {
        foreach ($cssCacheObj->getHttp2Preloads() as $http2Preload) {
            $src = $http2Preload['src'];
            unset($http2Preload['src']);
            $as = $http2Preload['as'];
            unset($http2Preload['as']);
            $this->http2Preload->add($src, $as, $http2Preload);
        }
    }

    private function handleCssFeatureHelpers(CacheObject $cssCacheObj): void
    {
        /** @see Fonts::generateCombinedFilesForFonts() */
        $this->getContainer()->get(Fonts::class)->generateCombinedFilesForFonts($cssCacheObj);
        /** @var LazyLoadExtended $lazyLoadExtended */
        $lazyLoadExtended = $this->getContainer()->get(LazyLoadExtended::class);
        $lazyLoadExtended->addCssBgImagesSelectors($cssCacheObj->getBgSelectors());

        foreach ($cssCacheObj->getLcpImages() as $lcpImage) {
            /** @see LCPImages::preloadConfiguredCssLcpImages() */
            $this->getContainer()->get(LCPImages::class)->preloadConfiguredCssLcpImages($lcpImage);
        }
    }

    public function cacheContent(FileInfo $fileInfo, bool $isLastKey): CacheObject
    {
        $function = [$this->combiner, 'cacheContent'];
        $args = [$fileInfo, $isLastKey];

        return $this->loadCache($function, $args, $id);
    }

    public function tagStorage($id, ?UriInterface $currentUrl = null): void
    {
        //If item not already set for tagging, set it
        $this->taggableCache->addItem($id, 'tag');
        $pageCache = $this->getContainer()->get(PageCache::class);

        if ($currentUrl === null) {
            $currentUrl = $pageCache->getCurrentPage();
        }

        //Always attempt to store tags, item could be set on another page
        $this->setStorageTags($id, $currentUrl);
    }

    private function setStorageTags(string $id, string $tag): void
    {
        $tags = $this->taggableCache->getTags($id);

        //If current tag not yet included, add it.
        if (is_array($tags) && !in_array($tag, $tags)) {
            $this->taggableCache->setTags($id, array_merge($tags, [$tag]));
        } elseif (empty($tags)) {
            $this->taggableCache->setTags($id, [$tag]);
        }
    }

    /**
     * @throws PregErrorException
     * @throws LaminasCacheExceptionInterface
     */
    private function updateOptimizeCssDelivery(
        CacheObject $cssCacheObj,
        HtmlElementInterface $element,
        bool $cssPreviouslyCached,
        bool $isLastKey
    ): void {
        /** @var CssProcessor $cssProcessor */
        $cssProcessor = $this->getContainer()->get(CssProcessor::class);
        $cssProcessor->setCacheObj($cssCacheObj);
        $cssProcessor->setIsLastKey($isLastKey);
        $htmlKey = $this->getHtmlKey();

        $function = [$this, 'updateCriticalCss'];
        $args = [$element, $cssProcessor, $htmlKey];
        $criticalCssId = $this->callbackCache->generateKey($function, $args);
        $cssCacheObj->setCriticalCssId($criticalCssId);

        if ($cssPreviouslyCached) {
            /** @var CacheObject $criticalCssObj */
            $criticalCssObj = $this->loadCache($function, $args, $id, $criticalCssAlreadyExisted);

            if ($criticalCssAlreadyExisted) {
                $this->getContainer()->get(CriticalCssDependencies::class)
                    ->addToCriticalCssAggregate($criticalCssObj->getCriticalCss())
                    ->addToDynamicCriticalCssAggregate($criticalCssObj->getDynamicCriticalCss())
                    ->addToPotentialCriticalCssAtRules($criticalCssObj->getPotentialCriticalCssAtRules());
            }

            $cssCacheObj->setCriticalCss($criticalCssObj->getImports() . $criticalCssObj->getCriticalCss());
            $cssCacheObj->setDynamicCriticalCss($criticalCssObj->getDynamicCriticalCss());
        } else {
            $this->callbackCache->getStorage()->setItem($criticalCssId, [$cssCacheObj]);
            $this->tagStorage($criticalCssId);
        }

        if ($isLastKey) {
            $cssProcessor->postProcessCriticalCss();
        }
    }

    public function updateCriticalCss(
        HtmlElementInterface $element,
        CssProcessor $cssProcessor,
        string $htmlKey
    ): CacheObject {
        $cssProcessor->setCssInfos(new FileInfo($element));
        $this->getContainer()->get(CriticalCssDependencies::class)
            ->addToPotentialCriticalCssAtRules($cssProcessor->getCacheObj()->getPotentialCriticalCssAtRules());
        $cssProcessor->optimizeCssDelivery();

        return $cssProcessor->getCacheObj();
    }

    private function getBelowFoldFontsKeyFrame(CacheObject $cssCacheObj): Link
    {
        $fileInfo = $this->getBelowFoldFontsFileInfo($cssCacheObj);
        $cacheObj = $this->getCombinedFiles([$fileInfo], $id, 'css');
        $url = $this->htmlManager->buildUrl($id, 'css', $cacheObj);
        $link = $this->htmlManager->getNewCssLink($url);
        $this->htmlManager->preloadStyleSheet($link, 'low');

        return $link;
    }

    private function getBelowFoldFontsFileInfo(CacheObject $cssCacheObj): FileInfo
    {
        $style = HtmlElementBuilder::style()
            ->addChild($cssCacheObj->getBelowFoldFontsKeyFrame());
        $fileInfo = new FileInfo($style);
        $fileInfo->setAlreadyProcessed(true);

        return $fileInfo;
    }
}
