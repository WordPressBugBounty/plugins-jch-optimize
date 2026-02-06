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
use _JchOptimizeVendor\V91\Joomla\Filesystem\File;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\FlushableInterface;
use _JchOptimizeVendor\V91\Laminas\Cache\Storage\StorageInterface;
use _JchOptimizeVendor\V91\Laminas\EventManager\EventManager;
use _JchOptimizeVendor\V91\Laminas\EventManager\EventManagerAwareInterface;
use _JchOptimizeVendor\V91\Laminas\EventManager\EventManagerAwareTrait;
use _JchOptimizeVendor\V91\Laminas\EventManager\SharedEventManagerInterface;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use JchOptimize\Core\CacheObject;
use JchOptimize\Core\Cdn\Cdn;
use JchOptimize\Core\Exception;
use JchOptimize\Core\Exception\PregErrorException;
use JchOptimize\Core\FeatureHelpers\DynamicJs;
use JchOptimize\Core\FileInfo;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Html\CssLayout\CssPlacementPlan;
use JchOptimize\Core\Html\Elements\Link;
use JchOptimize\Core\Html\Elements\Script;
use JchOptimize\Core\Html\Elements\Style;
use JchOptimize\Core\Html\JsLayout\JsPlacementPlan;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Platform\ProfilerInterface;
use JchOptimize\Core\Preloads\Http2Preload;
use JchOptimize\Core\Registry;
use JchOptimize\Core\Uri\Uri;
use JchOptimize\Core\Uri\Utils;

use function array_shift;
use function defined;
use function extension_loaded;
use function file_exists;
use function ini_get;
use function preg_replace;
use function str_replace;

use const JCH_DEBUG;
use const JCH_PRO;
use const PHP_EOL;

defined('_JCH_EXEC') or die('Restricted access');

class HtmlManager implements ContainerAwareInterface, EventManagerAwareInterface
{
    use ContainerAwareTrait;
    use EventManagerAwareTrait;

    protected $events = null;

    /**
     * Excluded JS grouped by the gate index they "fall" to.
     * Key: gateIndex (>=0 is an actual dontmove JS index, -1 means bottom-of-section)
     * Value: array of ['idx' => int, 'html' => string]
     *
     * @var array<int, array<int, array{idx:int,html:string}>>
     */
    private array $jsExcludedByGate = [];

    /**
     * Tracks which excluded JS (by idx) have already been emitted for a given gate.
     *
     * @var array<int, array<int,bool>>
     */
    private array $jsExcludedEmitted = [];

    public function __construct(
        private Registry $params,
        private HtmlProcessor $processor,
        private FilesManager $filesManager,
        private Cdn $cdn,
        private Http2Preload $http2Preload,
        private StorageInterface $cache,
        SharedEventManagerInterface $sharedEventManager,
        private ProfilerInterface $profiler,
        private PathsInterface $paths
    ) {
        $this->setEventManager(new EventManager($sharedEventManager));
    }

    /**
     * @throws PregErrorException
     */
    public function prependChildToHead(string $child): void
    {
        $headHtml = preg_replace(
            '#<title[^>]*+>#i',
            Helper::cleanReplacement($child) . "\n\t" . '\0',
            $this->processor->getHeadHtml(),
            1
        );
        $this->processor->setHeadHtml($headHtml);
    }

    public function getCriticalCssHtml(string $criticalCss, string $id): string
    {
        return HtmlElementBuilder::style()
            ->class('jchoptimize-critical-css')
            ->data('id', $id)
            ->addChild(PHP_EOL . $criticalCss . PHP_EOL)
            ->render();
    }

    /**
     * @throws PregErrorException
     */
    public function appendChildToHead(string $sChild): void
    {
        $sHeadHtml = $this->processor->getHeadHtml();
        $sHeadHtml = preg_replace(
            '#' . Parser::htmlClosingHeadTagToken() . '#i',
            Helper::cleanReplacement($sChild) . PHP_EOL . "\t" . '\0',
            $sHeadHtml,
            1
        );

        $this->processor->setHeadHtml($sHeadHtml);
    }

    public function appendChildToHTML(string $child, string $section): void
    {
        $regex = match ($section) {
            'head' => Parser::htmlClosingHeadTagToken(),
            'body' => Parser::htmlClosingBodyTagToken(),
        };
        $sSearchArea = preg_replace(
            "#{$regex}#si",
            "\t" . Helper::cleanReplacement($child) . PHP_EOL . '\0',
            $this->processor->getFullHtml(),
            1
        );
        $this->processor->setFullHtml($sSearchArea);
    }

    public function prependSiblingToElement(string $sibling, string $element, string $section): int
    {
        $n = PHP_EOL;
        $html = str_replace($element, "{$sibling}{$n}\t{$element}", $this->processor->{"get{$section}Html"}(), $count);
        $this->processor->{"set{$section}Html"}($html);

        return $count;
    }

    /**
     * @throws PregErrorException
     */
    public function setImgAttributes($aCachedImgAttributes): void
    {
        $sHtml = $this->processor->getBodyHtml();
        $this->processor->setBodyHtml(str_replace($this->processor->images[0], $aCachedImgAttributes, $sHtml));
    }

    public function buildUrl(string $id, string $type, CacheObject $cacheObj): UriInterface
    {
        $htaccess = $this->params->get('htaccess', 2);
        $uri = Utils::uriFor($this->paths->relAssetPath());

        switch ($htaccess) {
            case '1':
            case '3':
                $uri = ($htaccess == 3) ? $uri->withPath($uri->getPath() . '3') : $uri;
                $uri = $uri->withPath(
                    $uri->getPath() . $this->paths->rewriteBaseFolder()
                    . ($this->isGz() ? 'gz' : 'nz') . '/' . $id . '.' . $type
                );

                break;

            case '0':
                $uri = $uri->withPath($uri->getPath() . '2/jscss.php');

                $aVar = array();
                $aVar['f'] = $id;
                $aVar['type'] = $type;
                $aVar['gz'] = $this->isGZ() ? 'gz' : 'nz';

                $uri = Uri::withQueryValues($uri, $aVar);

                break;

            case '2':
            default:
                //Get cache Url, this will be embedded in the HTML
                $uri = Utils::uriFor($this->paths->cachePath());
                $uri = $uri->withPath(
                    $uri->getPath() . '/' . $type . '/' . $id . '.' . $type
                );// . ($this->isGz() ? '.gz' : '');

                $this->createStaticFiles($id, $type, $cacheObj);

                break;
        }

        return $this->cdn->loadCdnResource($uri);
    }

    public function isGZ(): bool
    {
        return ($this->params->get('gzip', 0) && extension_loaded('zlib') && !ini_get('zlib.output_compression')
            && (ini_get('output_handler') != 'ob_gzhandler'));
    }

    protected function createStaticFiles(string $id, string $type, CacheObject $cacheObj): void
    {
        JCH_DEBUG ? $this->profiler->start('CreateStaticFiles - ' . $type) : null;

        //Get cache filesystem path to create file
        $uri = Utils::uriFor($this->paths->cachePath(false));
        $uri = $uri->withPath($uri->getPath() . '/' . $type . '/' . $id . '.' . $type);
        //File path of combined file
        $combinedFile = (string)$uri;

        if (!file_exists($combinedFile)) {
            $content = $cacheObj->getContents();

            if ($content === '') {
                throw new Exception\RuntimeException('Error retrieving combined contents');
            }

            //Create file and any directory
            if (!File::write($combinedFile, $content)) {
                if ($this->cache instanceof FlushableInterface) {
                    $this->cache->flush();
                }

                throw new Exception\RuntimeException('Error creating static file');
            }
        }

        JCH_DEBUG ? $this->profiler->stop('CreateStaticFiles - ' . $type, true) : null;
    }

    public function getNewJsLink(string $url, bool $isDefer = false, bool $isASync = false): Script
    {
        $script = HtmlElementBuilder::script()->src($url);

        if ($isDefer) {
            $script->defer();
        }

        if ($isASync) {
            $script->async();
        }

        return $script;
    }

    public function preloadStyleSheet(Link|Style $element, string $fetchPriority = 'auto'): void
    {
        if ($element instanceof Link) {
            $attr = [
                'rel' => 'preload',
                'as' => 'style',
                'onload' => 'this.rel=\'stylesheet\'',
            ];
        } else {
            $media = $element->getMedia() ?: 'all';
            $attr = [
                'onload' => "this.media='{$media}'",
                'media' => 'print'
            ];
        }

        if ($fetchPriority != 'auto') {
            $attr['fetchpriority'] = $fetchPriority;
        }

        $element->attributes($attr);
    }

    public function removeAutoLcp(): void
    {
        $this->processor->processAutoLcp();
    }

    public function getDynamicCriticalCssHtml(string $css, string $id): string
    {
        return HtmlElementBuilder::style()
            ->class('jchoptimize-dynamic-critical-css')
            ->data('id', $id)
            ->addChild(PHP_EOL . $css . PHP_EOL)
            ->render();
    }

    /**
     * @param CssPlacementPlan $plan
     * @param (Link|Style)[] $combinedByGroup
     * @param CacheObject[] $cacheObjectByGroup
     * @param CacheObject[] $sensitiveCacheObjByOrds
     * @param Link|null $belowFoldFontsEl
     * @param Link|null $reducedBundleEl
     *
     * @return void
     */
    public function applyCssPlan(
        CssPlacementPlan $plan,
        array $combinedByGroup,
        array $cacheObjectByGroup,
        array $sensitiveCacheObjByOrds,
        ?Link $belowFoldFontsEl,
        ?Link $reducedBundleEl
    ): void {
        $html = $this->processor->getFullHtml();

        // 1) Remove all CSS except marker.
        //Remove excluded css
        $excluded = [];
        $marker = null;
        foreach ($plan->headBlocking as $placement) {
            if (!$placement->isProcessed) {
                if (!$placement->isMarker) {
                    $excluded[] = (string)$placement->item->node;
                } else {
                    $marker = $placement->item->node;
                }
            }
        }

        if (!empty($excluded)) {
            $html = str_replace($excluded, '', $html);
        }

        //Remove processed CSS
        foreach ($this->filesManager->cssReplacements as $groupIndex => $replacements) {
            if ($marker === null) {
                foreach ($plan->headBlocking as $placement) {
                    if ($placement->groupIndex === $groupIndex && $placement->isProcessed && $placement->isMarker) {
                        $marker = array_shift($replacements);
                        break;
                    }
                }
            }

            if (!empty($replacements)) {
                $html = str_replace($replacements, '', $html);
            }
        }

        // 2) Handle headBlocking (processed + excluded) CSS in <head>,
        //    preserving execution order.
        if (!empty($plan->headBlocking) && $marker !== null) {
            $insertion = '';

            foreach ($plan->headBlocking as $placement) {
                if ($placement->isProcessed) {
                    $cssEl = $combinedByGroup[$placement->groupIndex] ?? null;
                    if ($cssEl) {
                        $insertion .= "\t" . $cssEl->render() . PHP_EOL;
                    }
                } else {
                    $insertion .= "\t" . (string)$placement->item->node . PHP_EOL;
                }
            }

            $html = str_replace((string)$marker, $insertion, $html);
        }

        // 3) Inline critical CSS into <head>.
        if (!empty($plan->headInlineCritical)) {
            $insertion = '';

            foreach ($plan->headInlineCritical as $placement) {
                if ($placement->isSensitive) {
                    $sCacheObj = $sensitiveCacheObjByOrds[$placement->item->ordinal] ?? null;
                    if ($sCacheObj !== null && ($sCss = $sCacheObj->getCriticalCss()) !== '') {
                        $insertion .= "\t" . $this->getCriticalCssHtml($sCss, $sCacheObj->getCriticalCssId()) . PHP_EOL;
                    }
                    continue;
                }

                $cacheObj = $cacheObjectByGroup[$placement->groupIndex] ?? null;
                if ($cacheObj !== null && ($css = $cacheObj->getCriticalCss()) !== '') {
                    $insertion .= "\t" . $this->getCriticalCssHtml($css, $cacheObj->getCriticalCssId()) . PHP_EOL;
                }
            }

            $html = preg_replace(
                '#' . Parser::htmlClosingHeadTagToken() . '#si',
                Helper::cleanReplacement($insertion) . '\0',
                $html,
                1
            );
        }

        // 4) Dynamic critical CSS in <body>, above async CSS.
        if (!empty($plan->bodyInlineDynamicCritical)) {
            $insertion = '';

            foreach ($plan->bodyInlineDynamicCritical as $placement) {
                if ($placement->isSensitive) {
                    $sCacheObj = $sensitiveCacheObjByOrds[$placement->item->ordinal] ?? null;
                    if ($sCacheObj !== null && ($sCss = $sCacheObj->getDynamicCriticalCss()) !== '') {
                        $insertion .= "\t" . $this->getDynamicCriticalCssHtml(
                            $sCss,
                            $sCacheObj->getCriticalCssId()
                        ) . PHP_EOL;
                    }
                    continue;
                }

                $cacheObj = $cacheObjectByGroup[$placement->groupIndex] ?? null;
                if ($cacheObj !== null && ($css = $cacheObj->getDynamicCriticalCss()) !== '') {
                    $insertion .= "\t" . $this->getDynamicCriticalCssHtml(
                        $css,
                        $cacheObj->getCriticalCssId()
                    ) . PHP_EOL;
                }
            }

            $html = preg_replace(
                '#' . Parser::htmlClosingBodyTagToken() . '#si',
                Helper::cleanReplacement($insertion) . '\0',
                $html,
                1
            );
        }

        // 5) Async CSS in <body> (full processed CSS per-group).
        if (!empty($plan->bodyAsync)) {
            $insertion = '';

            foreach ($plan->bodyAsync as $placement) {
                if ($placement->isSensitive) {
                    $el = $placement->item->node;
                    $this->preloadStyleSheet($el, 'low');
                    $insertion .= "\t" . $el->render() . PHP_EOL;

                    continue;
                }

                $cssEl = $combinedByGroup[$placement->groupIndex] ?? null;
                if ($cssEl) {
                    $insertion .= "\t" . $cssEl->render() . PHP_EOL;
                }
            }

            $html = preg_replace(
                '#' . Parser::htmlClosingBodyTagToken() . '#si',
                Helper::cleanReplacement($insertion) . '\0',
                $html,
                1
            );
        }

        // 6) Extra async CSS blocks: below-the-fold fonts and reduced-unused bundle.
        $extraBody = '';

        if ($plan->appendBelowFoldFonts && $belowFoldFontsEl) {
            $extraBody .= "\t" . $belowFoldFontsEl->render() . PHP_EOL;
        }

        if ($plan->appendReducedUnusedBundle && $reducedBundleEl) {
            $extraBody .= "\t" . $reducedBundleEl->render() . PHP_EOL;
        }

        if ($extraBody) {
            $html = preg_replace(
                '#' . Parser::htmlClosingBodyTagToken() . '#si',
                Helper::cleanReplacement($extraBody) . '\0',
                $html,
                1
            );
        }

        if (!empty($plan->bodySensitiveDynamic)) {
            $insertion = '';

            foreach ($plan->bodySensitiveDynamic as $placement) {
                $el = $placement->item->node->type('jchoptimize-text/css');
                $insertion .= "\t" . $el->render() . PHP_EOL;
            }

            $html = preg_replace(
                '#' . Parser::htmlClosingBodyTagToken() . '#si',
                Helper::cleanReplacement($insertion) . '\0',
                $html,
                1
            );
        }

        $this->processor->setFullHtml($html);
    }

    public function applyJsPlan(JsPlacementPlan $plan, array $combinedByGroup, $section): void
    {
        $html = $this->processor->getFullHtml();

        // 1. Remove all processed JS that we're going to reinsert.
        foreach ($combinedByGroup as $groupIndex => $_) {
            $replacements = $this->filesManager->jsReplacements[$groupIndex] ?? [];
            if ($replacements) {
                $html = str_replace($replacements, '', $html);
            }
        }

        // 2. Remove excluded PEO scripts that are going to move (i.e. appear in plan).
        $movableExcluded = [];
        foreach ($plan->beforeGate as $gateOrdinal => $items) {
            foreach ($items as $placement) {
                if (!$placement->isProcessed) {
                    $movableExcluded[(string)$placement->item->node] = true;
                }
            }
        }
        foreach ($plan->bottom as $placement) {
            if (!$placement->isProcessed) {
                $movableExcluded[(string)$placement->item->node] = true;
            }
        }

        if ($movableExcluded) {
            $html = str_replace(array_keys($movableExcluded), '', $html);
        }

        // 3. Insert items before each gate.
        // Gates are JsItem; we have their node HTML to find them in the string.
        foreach ($plan->gates as $gate) {
            $gateHtml = (string)$gate->node;
            $before = $plan->beforeGate[$gate->ordinal] ?? [];

            if (!$before) {
                continue;
            }

            $insertion = '';
            foreach ($before as $placement) {
                if ($placement->isProcessed) {
                    $script = $combinedByGroup[$placement->groupIndex] ?? null;
                    if ($script) {
                        $insertion .= "\t" . $script->render() . PHP_EOL;
                    }
                } else {
                    $insertion .= "\t" . (string)$placement->item->node . PHP_EOL;
                }
            }

            $html = str_replace($gateHtml, $insertion . $gateHtml, $html);
        }

        // 4. Insert bottom-of-section items.
        if (!empty($plan->bottom)) {
            $insertion = '';
            $dynamicJs = null;
            if (JCH_PRO) {
                $dynamicJs = $this->getContainer()->get(DynamicJs::class);
            }
            foreach ($plan->bottom as $placement) {
                if (
                    JCH_PRO
                    && $this->params->get('pro_reduce_unused_js_enable', '0')
                    && $section === 'body'
                    && ($placement->isDeferable || $placement->isDeferred)
                    && !$placement->isExcluded
                    && $dynamicJs instanceof DynamicJs
                ) {
                    $script = $dynamicJs->prepareJsDynamicUrl($placement, $combinedByGroup);
                    if ($script) {
                        $insertion .= "\t" . (string)$script . PHP_EOL;
                    }
                } elseif (
                    $this->params->get('loadAsynchronous', '0')
                    && $section === 'body'
                    && $placement->isDeferable && !$placement->isExcluded
                ) {
                    if ($placement->isProcessed) {
                        $script = $combinedByGroup[$placement->groupIndex] ?? null;
                    } else {
                        $script = $placement->item->node;
                    }

                    if ($script) {
                        // Add defer/async if loadAsynchronous & bottom_js logic apply
                        $this->deferScript($script);
                        $insertion .= "\t" . $script->render() . PHP_EOL;
                    }
                } elseif ($placement->isProcessed) {
                    $script = $combinedByGroup[$placement->groupIndex] ?? null;
                    if ($script) {
                        $insertion .= "\t" . $script->render() . PHP_EOL;
                    }
                } else {
                    $insertion .= "\t" . (string)$placement->item->node . PHP_EOL;
                }
            }

            $bottomTagRx = $section === 'body' ? Parser::htmlClosingBodyTagToken() : Parser::htmlClosingHeadTagToken();
            $html = preg_replace(
                "#{$bottomTagRx}#si",
                Helper::cleanReplacement($insertion) . '\0',
                $html,
                1
            );
        }

        $this->processor->setFullHtml($html);
    }

    protected function cleanScript(string $script): string
    {
        if (!Helper::isXhtml($this->processor->getHtml())) {
            $script = str_replace(
                array(
                    '<script type="text/javascript"><![CDATA[',
                    '<script><![CDATA[',
                    ']]></script>'
                ),
                array('<script type="text/javascript">', '<script>', '</script>'),
                $script
            );
        }

        return $script;
    }

    public function getNewCssLink(string $url): Link
    {
        return HtmlElementBuilder::link()->rel('stylesheet')->href($url);
    }

    public function getModulePreloadLink(string $url): string
    {
        return HtmlElementBuilder::link()
            ->rel('modulepreload')
            ->href($url)
            ->fetchpriority('low')
            ->render();
    }

    public function preProcessHtml(): void
    {
        !JCH_DEBUG ?: $this->profiler->start('PreProcessHtml');

        $this->getEventManager()->trigger(__FUNCTION__, $this);

        !JCH_DEBUG ?: $this->profiler->stop('PreProcessHtml', true);
    }

    public function postProcessHtml(): void
    {
        !JCH_DEBUG ?: $this->profiler->start('PostProcessHtml');

        $this->getEventManager()->trigger(__FUNCTION__, $this);

        !JCH_DEBUG ?: $this->profiler->stop('PostProcessHtml', true);
    }

    /**
     * @throws PregErrorException
     */
    public function addCustomCss(): void
    {
        $css = '';

        $customCssEnable = $this->params->get('custom_css_enable', '0');
        $customCss = $this->params->get('custom_css', '');
        $mobileCss = $this->params->get('mobile_css', '');
        $desktopCss = $this->params->get('desktop_css', '');

        if ($customCssEnable && !empty($customCss)) {
            $css .= <<<CSS

{$customCss}

CSS;
        }

        if ($customCssEnable && !empty($mobileCss)) {
            $css .= <<<CSS

@media (max-width: 767.98px) {
    {$mobileCss}
}

CSS;
        }

        if ($customCssEnable && !empty($desktopCss)) {
            $css .= <<<CSS

@media (min-width: 768px) {
    {$desktopCss}
}

CSS;
        }

        if ($css !== '') {
            $style = HtmlElementBuilder::style()
                ->id('jchoptimize-custom-css')
                ->addChild($css)
                ->render();

            $this->appendChildToHead($style);
        }
    }

    private function deferScript(Script $element): void
    {
        if ($element->hasAttribute('src')) {
            $element->defer();
        } else {
            $element->type('module');
            $element->remove('defer');
            $element->remove('async');
        }
    }

    /**
     * @template T of Link|Script
     *
     * @param T $element The HTML element to add the data-file attribute to.
     * @param FileInfo $fileInfo The file information.
     *
     * @return T Returns the same type as the $element input (Link or Script).
     * @noinspection PhpDocSignatureInspection
     */
    public function addDataFileToElement(Link|Script $element, FileInfo $fileInfo): Link|Script
    {
        if ($this->params->get('debug', '') && !$this->params->get('combine_files', '0')) {
            if ($fileInfo->hasUri()) {
                $element->data('file', Utils::filename($fileInfo->getUri()));
            } elseif ($fileInfo->getType() == 'js') {
                $element->data('file', 'script');
            } else {
                $element->data('file', 'style');
            }
        }

        return $element;
    }
}
