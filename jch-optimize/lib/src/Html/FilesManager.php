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

use _JchOptimizeVendor\GuzzleHttp\Psr7\Uri;
use _JchOptimizeVendor\GuzzleHttp\Psr7\UriResolver;
use _JchOptimizeVendor\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\Psr\Http\Client\ClientInterface;
use _JchOptimizeVendor\Psr\Http\Message\UriInterface;
use CodeAlfa\Minify\Html;
use JchOptimize\Core\Container\ContainerAwareTrait;
use JchOptimize\Core\Exception\ExcludeException;
use JchOptimize\Core\Exception\PropertyNotFoundException;
use JchOptimize\Core\FeatureHelpers\Fonts;
use JchOptimize\Core\FileUtils;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Html\Elements\Link;
use JchOptimize\Core\Html\Elements\Script;
use JchOptimize\Core\Html\Elements\Style;
use JchOptimize\Core\Registry;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\UriComparator;
use JchOptimize\Platform\Excludes;

\defined('_JCH_EXEC') or exit('Restricted access');

/**
 * Handles the exclusion and replacement of files in the HTML based on set parameters, This class is called each
 * time a match is encountered in the HTML.
 */
class FilesManager implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @var bool Indicates if we can load the last javascript files asynchronously
     */
    public bool $bLoadJsAsync = \true;

    /**
     * @var bool Flagged when a CSS file is excluded PEO
     */
    public bool $cssExcludedPeo = \false;

    /**
     * @var bool Flagged when a CSS file is excluded IEO
     */
    public bool $cssExcludedIeo = \false;

    /**
     * @var bool Flagged anytime JavaScript files are excluded PEO
     */
    public bool $jsExcludedPeo = \false;

    /**
     * @var bool Flagged when a JavaScript file is excluded IEO
     */
    public bool $jsExcludedIeo = \false;

    /**
     * @var array Multidimensional array of css files to combine
     */
    public array $aCss = [[]];

    /**
     * @var array Multidimensional array of js files to combine
     */
    public array $aJs = [[]];

    /**
     * @var int Current index of js files to be combined
     */
    public int $iIndex_js = 0;

    /**
     * @var int Current index of css files to be combined
     */
    public int $iIndex_css = 0;

    /** @var array Javascript matches that will be excluded.
     *        Will be moved to the bottom of section if not selected in "don't move"
     */
    public array $aExcludedJs = [];

    /**
     * @var int Recorded incremented index of js files when the last file was excluded
     */
    public int $jsExcludedIndex = 0;

    /**
     * @var array Javascript files having the defer attribute
     */
    public array $defers = [[]];

    /**
     * @var array{
     *     excludes_peo:array{
     *         js:array<array-key, array{url?:string, script?:string, ieo?:string, dontmove?:string}>,
     *         css:string[],
     *         js_script:array<array-key, array{url?:string, script?:string, ieo?:string, dontmove?:string}>,
     *         css_script:string[]
     *     },
     *     critical_js:array{
     *         js:string[],
     *         script:string[]
     *     },
     *     remove:array{
     *         js:string[],
     *         css:string[]
     *     }
     * } $aExcludes Multidimensional array of excludes set in the parameters
     */
    public array $aExcludes = ['excludes_peo' => ['js' => [[]], 'css' => [], 'js_script' => [[]], 'css_script' => []], 'critical_js' => ['js' => [], 'script' => []], 'remove' => ['js' => [], 'css' => []]];

    /**
     * @var array Array of CSS matches to be removed
     */
    public array $cssReplacements = [[]];

    /**
     * @var array Array of JavaScript matched to be removed
     */
    public array $jsReplacements = [[]];

    /**
     * @var array Marks the place where combined JavaScript files will be placed in the HTML for the
     *            indicated index
     */
    public array $jsMarker = [];
    protected ?\JchOptimize\Core\Html\HtmlElementInterface $element = null;

    /**
     * @var array Array of matched elements holding links to CSS/Js files on the page
     */
    protected array $aMatches = [];

    /**
     * @var HtmlElementInterface|string String to replace the matched link
     */
    protected string|\JchOptimize\Core\Html\HtmlElementInterface $replacement = '';

    /**
     * @var string Type of exclude being processed (peo|ieo)
     */
    protected string $sCssExcludeType = '';

    /**
     * @var string Type of exclude being processed (peo|ieo)
     */
    protected string $sJsExcludeType = '';

    /**
     * @var array Array to hold files to check for duplicates
     */
    protected array $aUrls = [];

    private Registry $params;

    private ?ClientInterface $http;

    private FileUtils $fileUtils;

    /**
     * @var string Previous match of a script with module/async/defer attribute
     */
    private string $prevDeferMatches = '';

    /**
     * @var int Current index of the defers array
     */
    private int $deferIndex = -1;

    /**
     * @var array|null[]
     */
    private array $smartCombinePreviousParts = ['js' => null, 'css' => null];

    /**
     * @var array|int[]
     */
    private array $smartCombineCounters = ['js' => 0, 'css' => 0];

    /**
     * Private constructor, need to implement a singleton of this class.
     */
    public function __construct(Registry $params, FileUtils $fileUtils, ?ClientInterface $http)
    {
        $this->params = $params;
        $this->fileUtils = $fileUtils;
        $this->http = $http;
    }

    public function setExcludes(array $aExcludes): void
    {
        $this->aExcludes = $aExcludes;
    }

    public function processFiles(HtmlElementInterface $element): string
    {
        $this->element = $element;
        // By default, we'll return the match and save info later and what is to be removed
        $this->replacement = $element;

        try {
            if ($element instanceof Script) {
                if ($element->hasAttribute('src')) {
                    $this->checkUrls($element->getSrc());
                    $this->processJsUrl($element->getSrc());
                } elseif ($element->hasChildren()) {
                    $this->processJsContent($element->getChildren()[0]);
                }
            }
            if ($element instanceof Link) {
                $this->checkUrls($element->getHref());
                $this->processCssUrl($element->getHref());
            }
            if ($element instanceof Style && $element->hasChildren()) {
                $this->processCssContent($element->getChildren()[0]);
            }
        } catch (ExcludeException $e) {
        }

        return (string) $this->replacement;
    }

    /**
     * Checks if a file appears more than once on the page so that it's not duplicated in the combined files.
     *
     * @param UriInterface $uri Url of file
     *
     * @return bool True if already included
     *
     * @since
     */
    public function isDuplicated(UriInterface $uri): bool
    {
        $url = Uri::composeComponents('', $uri->getAuthority(), $uri->getPath(), $uri->getQuery(), '');
        $return = \in_array($url, $this->aUrls);
        if (!$return) {
            $this->aUrls[] = $url;
        }

        return $return;
    }

    /**
     * @param mixed $addToExcludes
     *
     * @return never
     *
     * @throws ExcludeException
     */
    public function excludeJsIEO($addToExcludes = \true)
    {
        $this->jsExcludedIeo = \true;
        $this->sJsExcludeType = 'ieo';
        if ($addToExcludes) {
            $this->aExcludedJs[] = $this->getElement();
        }

        throw new ExcludeException();
    }

    protected function getElement(): HtmlElementInterface
    {
        if ($this->element instanceof \JchOptimize\Core\Html\HtmlElementInterface) {
            return $this->element;
        }

        throw new PropertyNotFoundException('HTMLElement not set in '.\get_class($this));
    }

    /**
     * @throws ExcludeException
     */
    private function checkUrls(UriInterface $uri): void
    {
        // Exclude invalid urls
        if ('data' == $uri->getScheme()) {
            if ($this->getElement() instanceof Script) {
                $this->excludeJsIEO();
            } else {
                $this->excludeCssIEO();
            }
        }
    }

    /**
     * @throws ExcludeException
     */
    private function processCssUrl(UriInterface $uri): void
    {
        // Get media value if attribute set
        $media = $this->getMediaAttribute();
        // process google font files or other CSS files added to be optimized
        if ('fonts.googleapis.com' == $uri->getHost() || Helper::findExcludes(Helper::getArray($this->params->get('pro_optimize_font_files', [])), (string) $uri)) {
            if (JCH_PRO) {
                // @see Fonts::pushFileToFontsArray()
                $this->container->get(Fonts::class)->pushFileToFontsArray($uri, $media);
                $this->replacement = '';
            }
            // if Optimize Fonts not enabled just return Google Font files. Google fonts will serve a different version
            // for different browsers and creates problems when we try to cache it.
            if ('fonts.googleapis.com' == $uri->getHost() && !$this->params->get('pro_optimizeFonts_enable', '0')) {
                $this->replacement = $this->getElement();
            }
            $this->excludeCssIEO();
        }
        if ($this->isDuplicated($uri)) {
            $this->replacement = '';
            $this->excludeCssIEO();
        }
        // process excludes for css urls
        if ($this->excludeGenericUrls($uri) || Helper::findExcludes(@$this->aExcludes['excludes_peo']['css'], (string) $uri)) {
            // If Optimize CSS Delivery enabled, always exclude IEO
            if ($this->params->get('optimizeCssDelivery_enable', '0')) {
                $this->excludeCssIEO();
            } else {
                $this->excludeCssPEO();
            }
        }
        $this->processSmartCombine($uri);
        // File was not excluded
        $this->cssExcludedPeo = \false;
        $this->cssExcludedIeo = \false;
        // Record file info for download
        $this->aCss[$this->iIndex_css][] = ['url' => $uri, 'media' => $media];
        // Record match to be replaced
        $this->cssReplacements[$this->iIndex_css][] = $this->getElement();
    }

    private function getMediaAttribute(): string
    {
        return (string) $this->getElement()->attributeValue('media') ?: '';
    }

    /**
     * @return never
     *
     * @throws ExcludeException
     */
    private function excludeCssIEO()
    {
        $this->cssExcludedIeo = \true;
        $this->sCssExcludeType = 'ieo';

        throw new ExcludeException();
    }

    private function excludeGenericUrls(UriInterface $uri): bool
    {
        // Exclude unsupported urls
        if ('https' == $uri->getScheme() && !\extension_loaded('openssl')) {
            return \true;
        }
        $resolvedUri = UriResolver::resolve(SystemUri::currentUri(), $uri);
        // Exclude files from external extensions if parameter not set (PEO)
        if (!$this->params->get('includeAllExtensions', '0')) {
            if (!UriComparator::isCrossOrigin($resolvedUri) && \preg_match('#'.Excludes::extensions().'#i', (string) $uri)) {
                return \true;
            }
        }
        // Exclude all external and dynamic files
        if (!$this->params->get('phpAndExternal', '0')) {
            if (UriComparator::isCrossOrigin($resolvedUri) || !Helper::isStaticFile($uri->getPath())) {
                return \true;
            }
        }

        return \false;
    }

    /**
     * Called when current match should be excluded PEO, which means, if index not already incremented, do so now.
     *
     * @return never
     *
     * @throws ExcludeException
     */
    private function excludeCssPEO()
    {
        // if previous file was not excluded increment css index
        if (!$this->cssExcludedPeo && !empty($this->cssReplacements[0])) {
            ++$this->iIndex_css;
        }
        $this->cssExcludedPeo = \true;
        $this->sCssExcludeType = 'peo';

        throw new ExcludeException();
    }

    private function processSmartCombine(UriInterface $uri): void
    {
        if ($this->params->get('pro_smart_combine', '0')) {
            $type = $this->getElement() instanceof Script ? 'js' : 'css';
            $fileUri = UriResolver::resolve(SystemUri::currentUri(), $uri);
            $filePath = $fileUri->getPath();
            $currentParts = \array_filter(\explode('/', $filePath));
            \array_pop($currentParts);
            ++$this->smartCombineCounters[$type];
            if (!empty($this->smartCombinePreviousParts[$type])) {
                if ($this->smartCombinePreviousParts[$type] != $currentParts || $this->smartCombineCounters[$type] > 3) {
                    if ('js' == $type) {
                        // Don't increase index if we're in an exclude. Index already incremented
                        if (!$this->jsExcludedPeo) {
                            ++$this->iIndex_js;
                        }
                        $this->bLoadJsAsync = \false;
                    } else {
                        // Don't increase index if we're in an exclude. Index already incremented
                        if (!$this->cssExcludedPeo) {
                            ++$this->iIndex_css;
                        }
                    }
                    $this->smartCombineCounters[$type] = 0;
                }
            }
            $this->smartCombinePreviousParts[$type] = $currentParts;
        }
    }

    /**
     * @throws ExcludeException
     */
    private function processCssContent(string $content): void
    {
        $media = $this->getMediaAttribute();
        if (Helper::findExcludes(@$this->aExcludes['excludes_peo']['css_script'], $content, 'css') || !$this->params->get('inlineStyle', '0') || $this->params->get('excludeAllStyles', '0')) {
            if ($this->params->get('optimizeCssDelivery_enable', '0')) {
                $this->excludeCssIEO();
            } else {
                $this->excludeCssPEO();
            }
        }
        $this->cssExcludedPeo = \false;
        $this->cssExcludedIeo = \false;
        $this->aCss[$this->iIndex_css][] = ['content' => Html::cleanScript($content, 'css'), 'media' => $media];
        $this->cssReplacements[$this->iIndex_css][] = $this->getElement();
    }

    /**
     * @throws ExcludeException
     */
    private function processJsUrl(UriInterface $uri): void
    {
        if ($this->isDuplicated($uri)) {
            $this->excludeJsIEO();
        }
        // Add all defers, modules and nomodules to the defer array, incrementing the index each time a
        // different type is encountered
        $deferAttributes = ['type' => 'module', 'nomodule' => \true, 'async' => \true, 'defer' => \true];
        foreach ($this->aExcludes['excludes_peo']['js'] as $exclude) {
            if (!empty($exclude['url']) && Helper::findExcludes([$exclude['url']], (string) $uri)) {
                // If dont move, don't add to excludes
                $addToExcludes = !isset($exclude['dontmove']);
                // Handle js files IEO
                if (isset($exclude['ieo'])) {
                    $this->excludeJsIEO($addToExcludes);
                } else {
                    // Prepare PEO excludes for js urls
                    $this->excludeJsPEO($addToExcludes);
                }
            }
        }
        if (($attributeType = $this->getElement()->firstofAttributes($deferAttributes)) !== \false) {
            if ($attributeType != $this->prevDeferMatches) {
                ++$this->deferIndex;
                $this->prevDeferMatches = $attributeType;
            }
            $this->defers[$this->deferIndex][] = ['attributeType' => $attributeType, 'script' => $this->getElement(), 'url' => $uri];
            $this->bLoadJsAsync = \false;
            $this->excludeJsIEO(\false);
        }
        if ($this->excludeGenericUrls($uri)) {
            $this->excludeJsPEO();
        }
        $this->processSmartCombine($uri);
        $this->responseToPreviousExclude();
        $this->jsExcludedPeo = \false;
        $this->jsExcludedIeo = \false;
        $this->aJs[$this->iIndex_js][] = ['url' => $uri];
        $this->jsReplacements[$this->iIndex_js][] = $this->getElement();
    }

    /**
     * @param mixed $addToExcludes
     *
     * @return never
     *
     * @throws ExcludeException
     */
    private function excludeJsPEO($addToExcludes = \true)
    {
        // If previous file was not excluded, update marker
        if (!$this->jsExcludedPeo) {
            $marker = $this->getElement()->data('jch', 'js'.$this->iIndex_js);
            $this->jsMarker = \array_pad($this->jsMarker, $this->iIndex_js + 1, $marker);
        }
        if ($addToExcludes) {
            $this->aExcludedJs[] = $this->getElement();
        }
        // Record index of last excluded file
        $this->jsExcludedIndex = $this->iIndex_js;
        // Can no longer load last combined file asynchronously
        $this->bLoadJsAsync = \false;
        $this->jsExcludedPeo = \true;
        $this->sJsExcludeType = 'peo';

        throw new ExcludeException();
    }

    /**
     * @throws ExcludeException
     */
    private function processJsContent(string $content): void
    {
        foreach ($this->aExcludes['excludes_peo']['js_script'] as $exclude) {
            if (!empty($exclude['script']) && Helper::findExcludes([$exclude['script']], $content)) {
                // If don't move, don't add to excludes
                $addToExcludes = !isset($exclude['dontmove']);
                if (isset($exclude['ieo'])) {
                    // process PEO excludes for js scripts
                    $this->excludeJsIEO($addToExcludes);
                } else {
                    // Prepare IEO excludes for js scripts
                    $this->excludeJsPEO($addToExcludes);
                }
            }
        }
        // Exclude all scripts if options set
        if (!$this->params->get('inlineScripts', '0') || $this->params->get('excludeAllScripts', '0')) {
            $this->excludeJsPEO();
        }
        // Add all modules and nomodules to the defer array, incrementing the index each time a
        // different type is encountered. The defer and async attribute on inline scripts are ignored
        $deferAttributes = ['type' => 'module', 'nomodule' => \true];
        if (($attributeType = $this->getElement()->firstOfAttributes($deferAttributes)) !== \false) {
            if ($attributeType != $this->prevDeferMatches) {
                ++$this->deferIndex;
                $this->prevDeferMatches = $attributeType;
            }
            $this->defers[$this->deferIndex][] = ['attributeType' => $attributeType, 'script' => $this->getElement(), 'content' => $content];
            $this->bLoadJsAsync = \false;
            $this->excludeJsIEO(\false);
        }
        $this->responseToPreviousExclude();
        $this->jsExcludedPeo = \false;
        $this->jsExcludedIeo = \false;
        $this->aJs[$this->iIndex_js][] = ['content' => Html::cleanScript($content, 'js')];
        $this->jsReplacements[$this->iIndex_js][] = $this->getElement();
    }

    private function responseToPreviousExclude(): void
    {
        // If previous file was excluded PEO, update index
        if ($this->jsExcludedPeo) {
            ++$this->iIndex_js;
        }
    }
}
