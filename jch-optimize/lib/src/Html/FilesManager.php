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

use _JchOptimizeVendor\V91\GuzzleHttp\Psr7\UriResolver;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareTrait;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use CodeAlfa\Minify\Css;
use CodeAlfa\Minify\Js;
use JchOptimize\Core\Cdn\Cdn;
use JchOptimize\Core\Exception\ExcludeException;
use JchOptimize\Core\Exception\PropertyNotFoundException;
use JchOptimize\Core\FeatureHelpers\Fonts;
use JchOptimize\Core\FileInfo;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Html\CssLayout\CssItem;
use JchOptimize\Core\Html\Elements\Link;
use JchOptimize\Core\Html\Elements\Script;
use JchOptimize\Core\Html\Elements\Style;
use JchOptimize\Core\Html\Excludes\SectionExcludes;
use JchOptimize\Core\Html\JsLayout\JsItem;
use JchOptimize\Core\Platform\ExcludesInterface;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Registry;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\Uri;
use JchOptimize\Core\Uri\UriComparator;

use function array_column;
use function array_filter;
use function defined;
use function extension_loaded;
use function get_class;
use function in_array;
use function preg_match;
use function str_contains;

defined('_JCH_EXEC') or die('Restricted access');

/**
 * Handles the exclusion and replacement of files in the HTML based on set parameters, This class is called each
 * time a match is encountered in the HTML
 */
class FilesManager implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @var bool Flagged when a CSS file is excluded PEO
     */
    public bool $cssExcludedPeo = false;

    /**
     * @var bool Flagged when a CSS file is excluded IEO
     */
    public bool $cssExcludedIeo = false;
    /**
     * @var bool Flagged anytime JavaScript files are excluded PEO
     */
    public bool $jsExcludedPeo = false;

    /**
     * @var bool Flagged when a JavaScript file is excluded IEO
     */
    public bool $jsExcludedIeo = false;

    /**
     * @var bool Flagged when a sensitive CSS file is encountered
     */
    protected bool $cssSensitive = false;

    /**
     * @var bool Flagged when a sensitive JS file is encountered
     */
    protected bool $jsSensitive = false;
    /**
     * @var array $aCss Multidimensional array of css files to combine
     */
    public array $aCss = [[]];

    /**
     * @var array $aJs Multidimensional array of js files to combine
     */
    public array $aJs = [[]];

    /**
     * @var int $iIndex_js Current index of js files to be combined
     */
    public int $iIndex_js = 0;

    /**
     * @var int $iIndex_css Current index of css files to be combined
     */
    public int $iIndex_css = 0;

    protected ?HtmlElementInterface $element = null;

    public ?SectionExcludes $sectionExcludes = null;

    /**
     * @var array $cssReplacements Array of CSS matches to be removed
     */
    public array $cssReplacements = [[]];

    /**
     * @var array $jsReplacements Array of JavaScript matched to be removed
     */
    public array $jsReplacements = [[]];

    /**
     * @var string|HtmlElementInterface $replacement String to replace the matched link
     */
    protected string|HtmlElementInterface $replacement = '';

    /**
     * @var string $sCssExcludeType Type of exclude being processed (peo|ieo)
     */
    protected string $sCssExcludeType = '';

    /**
     * @var string $sJsExcludeType Type of exclude being processed (peo|ieo)
     */
    protected string $sJsExcludeType = '';

    /**
     * @var array  Array to hold files to check for duplicates
     */
    protected array $aUrls = [];

    /** @var CssItem[] */
    public array $cssTimeLine = [];

    /** @var JsItem[] */
    public array $jsTimeLine = [];

    /**
     * Private constructor, need to implement a singleton of this class
     */
    public function __construct(
        private Registry $params,
        private ExcludesInterface $platformExcludes,
    ) {
    }

    public function setExcludes(SectionExcludes $sectionExcludes): void
    {
        $this->sectionExcludes = $sectionExcludes;
    }

    /**
     * @param HtmlElementInterface $element
     * @return string
     */
    public function processFiles(HtmlElementInterface $element): string
    {
        $this->element = $element;
        //By default, we'll return the match and save info later and what is to be removed
        $this->replacement = $element;

        try {
            $this->checkUrls($element);

            if ($element instanceof Script) {
                if ($element->hasAttribute('src')) {
                    $this->processJsUrl($element);
                } elseif ($element->hasChildren()) {
                    $this->processJsContent($element);
                }
            }

            if ($element instanceof Link) {
                $this->processCssUrl($element);
            }

            if ($element instanceof Style && $element->hasChildren()) {
                $this->processCssContent($element);
            }
        } catch (ExcludeException) {
        }

        return (string)$this->replacement;
    }

    protected function getElement(): HtmlElementInterface
    {
        if ($this->element instanceof HtmlElementInterface) {
            return $this->element;
        }

        throw new PropertyNotFoundException('HTMLElement not set in ' . get_class($this));
    }

    /**
     * @throws ExcludeException
     */
    private function checkUrls(HtmlElementInterface $element): void
    {
        //Exclude invalid urls
        if (
            $element instanceof Script
            && ($uri = $element->getSrc()) instanceof UriInterface
            && $uri->getScheme() == 'data'
        ) {
            $this->excludeJsIEO($element);
        } elseif (
            $element instanceof Link
            && ($uri = $element->getHref()) instanceof UriInterface
            && $uri->getScheme() == 'data'
        ) {
            $this->excludeCssIEO();
        }
    }

    /**
     * @throws ExcludeException
     */
    private function processCssUrl(Link $link): void
    {
        $uri = $link->getHref();

        if (!$uri instanceof UriInterface) {
            $this->excludeCssIEO();
        }

        //Get media value if attribute set
        $media = $this->getMediaAttribute();

        if ($media == 'none' || $this->mediaValueWillChangeOnLoad($link)) {
            $this->excludeCssIEO();
        }

        //process google font files or other CSS files added to be optimized
        if (
            $uri->getHost() == 'fonts.googleapis.com'
            || Helper::findExcludes(
                Helper::getArray($this->params->get('pro_optimize_font_files', [])),
                (string)$uri
            )
        ) {
            if (JCH_PRO) {
                /** @see Fonts::pushFileToFontsArray() */
                $this->container->get(Fonts::class)->pushFileToFontsArray($uri, $media);
                $this->replacement = '';
            }

            //if Optimize Fonts not enabled just return Google Font files. Google fonts will serve a different version
            //for different browsers and creates problems when we try to cache it.
            if ($uri->getHost() == 'fonts.googleapis.com' && !$this->params->get('pro_optimizeFonts_enable', '0')) {
                $this->replacement = $this->getElement();
            }

            $this->excludeCssIEO();
        }

        if ($this->isSensitiveExternal($link, $uri)) {
            $this->recordSensitiveCss($link, $media);

            // No PEO/IEO excludes, no adding to $aCss; planner will later:
            // - use it for critical CSS extraction
            // - schedule original href async/dynamic
            return;
        }

        //process excludes for css urls
        if (
            $this->excludeGenericUrls($uri)
            || (
                $this->sectionExcludes !== null
                && Helper::findExcludes($this->sectionExcludes->peo->cssUrls, (string)$uri)
            )
        ) {
            //If Optimize CSS Delivery enabled, always exclude IEO
            if ($this->params->get('optimizeCssDelivery_enable', '0')) {
                $this->excludeCssIEO();
            } else {
                $this->excludeCssPEO($link, $media);
            }
        }

        $this->processCssElement($link, $media);
    }

    private function getMediaAttribute(): string
    {
        return (string)($this->getElement()->attributeValue('media') ?: '');
    }

    /**
     * @return never
     * @throws ExcludeException
     *
     */
    private function excludeCssIEO()
    {
        $this->cssExcludedIeo = true;
        $this->sCssExcludeType = 'ieo';

        throw new ExcludeException();
    }

    private function excludeGenericUrls(UriInterface $uri): bool
    {
        //Exclude unsupported urls
        if ($uri->getScheme() == 'https' && !extension_loaded('openssl')) {
            return true;
        }

        $resolvedUri = UriResolver::resolve(SystemUri::currentUri(), $uri);
        $cdn = $this->getContainer()->get(Cdn::class);
        $path = $this->getContainer()->get(PathsInterface::class);

        //Exclude files from external extensions if parameter not set (PEO)
        if (!$this->params->get('includeAllExtensions', '0')) {
            if (
                UriComparator::existsLocally($resolvedUri, $cdn, $path)
                && preg_match('#' . $this->platformExcludes->extensions() . '#i', (string)$uri)
            ) {
                return true;
            }
        }

        //Exclude all external and dynamic files
        if (!$this->params->get('phpAndExternal', '0')) {
            if (
                !UriComparator::existsLocally($resolvedUri, $cdn, $path)
                || !Helper::isStaticFile($uri->getPath())
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Called when current match should be excluded PEO, which means, if index not already incremented, do so now.
     *
     * @return never
     * @throws ExcludeException
     */
    private function excludeCssPEO(Link|Style $element, ?string $media = null)
    {
        $this->cssExcludedPeo = true;
        $this->sCssExcludeType = 'peo';

        $ordinal = count($this->cssTimeLine);

        $this->cssTimeLine[] = new CssItem(
            ordinal: count($this->cssTimeLine),
            groupIndex: null,
            isProcessed: false,
            isExcluded: true,
            isInline: $element instanceof Style,
            isMarker: $ordinal === 0,
            isSensitive: false,
            media: $media,
            node: $element
        );

        throw new ExcludeException();
    }

    /**
     * Checks if a file appears more than once on the page so that it's not duplicated in the combined files
     *
     * @param UriInterface $uri Url of file
     *
     * @return bool        True if already included
     * @since
     */
    public function isDuplicated(UriInterface $uri): bool
    {
        $url = Uri::composeComponents('', $uri->getAuthority(), $uri->getPath(), $uri->getQuery(), '');
        $return = in_array($url, $this->aUrls);

        if (!$return) {
            $this->aUrls[] = $url;
        }

        return $return;
    }

    private function updateIndex(): void
    {
        $combineFiles = $this->params->get('combine_files', '0');
        $element = $this->getElement();

        if ($element instanceof Script && !empty($this->aJs[$this->iIndex_js] ?? [])) {
            if (!$combineFiles) {
                $this->iIndex_js++;
            } elseif ($this->jsExcludedPeo || $this->jsSensitive) {
                $this->iIndex_js++;
            }
        } elseif (
            ($element instanceof Link || $element instanceof Style)
            && !empty($this->aCss[$this->iIndex_css] ?? [])
        ) {
            // Same logic for CSS
            if (!$combineFiles) {
                $this->iIndex_css++;
            } elseif ($this->cssExcludedPeo || $this->cssSensitive) {
                $this->iIndex_css++;
            }
        }
    }

    /**
     * @throws ExcludeException
     */
    private function processCssContent(Style $style): void
    {
        $content = $style->getChildren()[0];
        $contentOpt = Css::optimize($content);
        if (
            $this->sectionExcludes !== null
            && Helper::findExcludes($this->sectionExcludes->peo->cssScriptPatterns, $contentOpt, 'css')
            || !$this->params->get('inlineStyle', '0')
            || $this->params->get('excludeAllStyles', '0')
        ) {
            if ($this->params->get('optimizeCssDelivery_enable', '0')) {
                $this->excludeCssIEO();
            } else {
                $this->excludeCssPEO($style);
            }
        }

        $this->processCssElement($style);
    }

    /**
     * @throws ExcludeException
     */
    private function processJsUrl(Script $script): void
    {
        $uri = $script->getSrc();

        if (!$uri instanceof UriInterface) {
            $this->excludeJsIEO($script);
        }

        if ($this->isDuplicated($uri)) {
            $this->replacement = '';
            return;
        }

        // --- sensitivity check for external JS ---
        if ($this->isSensitiveExternal($script, $uri)) {
            $this->recordSensitiveJs($script);

            // We don't process or exclude it; just keep it in the timeline for the planner.
            return;
        }

        if ($this->sectionExcludes !== null) {
            // Process PEO / IEO excludes for JS URLs (including dontmove)
            foreach ($this->sectionExcludes->peo->jsUrlRules as $rule) {
                if ($rule->url !== null && Helper::findExcludes([$rule->url], (string)$uri)) {
                    $dontMove = $rule->dontMove;

                    // Handle IEO
                    if ($rule->ignoreExecutionOrder) {
                        $this->excludeJsIEO($script, $dontMove);
                    } else {
                        // Normal PEO excludes
                        $this->excludeJsPEO($script, $dontMove);
                    }
                }
            }
        }

        if ($this->excludeGenericUrls($uri)) {
            $this->excludeJsPEO($script);
        }

        $this->maybeProcessScript($script);
    }

    /**
     * @return never
     * @throws ExcludeException
     */
    public function excludeJsIEO(Script $script, $dontMove = false)
    {
        $this->jsExcludedIeo = true;
        $this->sJsExcludeType = 'ieo';

        $this->jsTimeLine[] = new JsItem(
            ordinal: count($this->jsTimeLine),
            groupIndex: null,
            isProcessed: false,
            isExcluded: true,
            isGate: $dontMove,
            isIeo: true,
            isDeferred: Helper::isScriptDeferred($script),
            isSensitive: false,
            node: $script
        );

        throw new ExcludeException();
    }

    /**
     * @return never
     * @throws ExcludeException
     */
    private function excludeJsPEO(Script $script, $dontMove = false)
    {
        $this->jsExcludedPeo = true;
        $this->sJsExcludeType = 'peo';

        $this->jsTimeLine[] = new JsItem(
            ordinal: count($this->jsTimeLine),
            groupIndex: null,
            isProcessed: false,
            isExcluded: true,
            isGate: $dontMove,
            isIeo: false,
            isDeferred: Helper::isScriptDeferred($script),
            isSensitive: false,
            node: $script
        );

        throw new ExcludeException();
    }

    /**
     * @throws ExcludeException
     */
    private function processJsContent(Script $script): void
    {
        $content = $script->getChildren()[0];

        //Exclude all scripts if options set
        if (
            !$this->params->get('inlineScripts', '0')
            || $this->params->get('excludeAllScripts', '0')
        ) {
            $this->excludeJsPEO($script);
        }

        if ($this->sectionExcludes !== null) {
            foreach ($this->sectionExcludes->peo->jsScriptRules as $rule) {
                if ($rule->script !== null && Helper::findExcludes([$rule->script], Js::optimize($content))) {
                    //If 'dontmove', don't add to excludes
                    $dontMove = $rule->dontMove;

                    if ($rule->ignoreExecutionOrder) {
                        //process IEO excludes for js scripts
                        $this->excludeJsIEO($script, $dontMove);
                    } else {
                        //Prepare PEO excludes for js scripts
                        $this->excludeJsPEO($script, $dontMove);
                    }
                }
            }
        }

        $this->maybeProcessScript($script);
    }

    private function mediaValueWillChangeOnLoad(Link $link): bool
    {
        return str_contains((string)$link->attributeValue('onload'), 'media');
    }

    /**
     * @param Script $script
     * @return void
     */
    private function maybeProcessScript(Script $script): void
    {
        $isDeferred = Helper::isScriptDeferred($script);
        $isProcessed = false;
        $groupIndex = null;

        if (!$isDeferred) {
            $this->updateIndex();
            $this->aJs[$this->iIndex_js][] = new FileInfo(clone $script);
            $this->jsReplacements[$this->iIndex_js][] = $script;
            $isProcessed = true;
            $groupIndex = $this->iIndex_js;
        }

        //These properties must only be set after index is (maybe) updated
        $this->jsExcludedPeo = false;
        $this->jsExcludedIeo = false;
        $this->cssSensitive = false;

        $existingGroupIndexes = array_filter(
            array_column($this->jsTimeLine, 'groupIndex'),
            fn($a) => $a !== null
        );

        if ($groupIndex === null || !in_array($groupIndex, $existingGroupIndexes)) {
            $this->jsTimeLine[] = new JsItem(
                ordinal: count($this->jsTimeLine),
                groupIndex: $groupIndex,
                isProcessed: $isProcessed,
                isExcluded: false,
                isGate: false,
                isIeo: false,
                isDeferred: $isDeferred,
                isSensitive: false,
                node: !$this->params->get('combine_files', 0) ? clone $script : null
            );
        }
    }

    private function processCssElement(Link|Style $element, ?string $media = null): void
    {
        $this->updateIndex();
        //These properties must be set only after index is updated
        $this->cssExcludedPeo = false;
        $this->cssExcludedIeo = false;
        $this->cssSensitive = false;

        $this->aCss[$this->iIndex_css][] = new FileInfo(clone $element);
        $this->cssReplacements[$this->iIndex_css][] = $element;
        $groupIndex = $this->iIndex_css;
        $ordinal = count($this->cssTimeLine);

        $existingGroupIndexes = array_filter(
            array_column($this->cssTimeLine, 'groupIndex'),
            fn($a) => $a !== null
        );

        if (!in_array($groupIndex, $existingGroupIndexes)) {
            $this->cssTimeLine[] = new CssItem(
                ordinal: $ordinal,
                groupIndex: $groupIndex,
                isProcessed: true,
                isExcluded: false,
                isInline: false,
                isMarker: $ordinal === 0,
                isSensitive: false,
                media: $media,
                node: !$this->params->get('combine_files', 0) ? clone $element : null
            );
        }
    }

    private function hasSensitiveAttributes(HtmlElementInterface $element): bool
    {
        return $element->hasAttribute('integrity')
            || $element->hasAttribute('crossorigin')
            || $element->hasAttribute('referrerpolicy');
    }

    private function isExternal(UriInterface $uri): bool
    {
        $resolvedUri = UriResolver::resolve(SystemUri::currentUri(), $uri);

        $cdn = $this->getContainer()->get(Cdn::class);
        $path = $this->getContainer()->get(PathsInterface::class);

        return !UriComparator::existsLocally($resolvedUri, $cdn, $path);
    }

    private function isSensitiveExternal(HtmlElementInterface $element, UriInterface $uri): bool
    {
        return $this->hasSensitiveAttributes($element) && $this->isExternal($uri);
    }

    private function recordSensitiveJs(Script $script): void
    {
        $this->jsSensitive = true;
        $this->replacement = '';

        $this->jsTimeLine[] = new JsItem(
            ordinal: count($this->jsTimeLine),
            groupIndex: null,               // not part of any processed group
            isProcessed: false,
            isExcluded: false,
            isGate: false,
            isIeo: false,
            isDeferred: Helper::isScriptDeferred($script),
            isSensitive: true,
            node: clone $script
        );
    }

    private function recordSensitiveCss(Link $link, ?string $media): void
    {
        $this->cssSensitive = true;
        $this->replacement = '';
        $ordinal = count($this->cssTimeLine);

        $this->cssTimeLine[] = new CssItem(
            ordinal: $ordinal,
            groupIndex: null,               // not part of any processed group
            isProcessed: false,
            isExcluded: false,
            isInline: false,
            isMarker: $ordinal === 0,
            isSensitive: true,
            media: $media,
            node: clone $link
        );
    }
}
