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
use _JchOptimizeVendor\V91\Psr\Http\Client\ClientInterface;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use CodeAlfa\Minify\Css;
use CodeAlfa\Minify\Js;
use JchOptimize\Core\Cdn\Cdn;
use JchOptimize\Core\Exception\ExcludeException;
use JchOptimize\Core\Exception\PropertyNotFoundException;
use JchOptimize\Core\FeatureHelpers\DynamicJs;
use JchOptimize\Core\FeatureHelpers\Fonts;
use JchOptimize\Core\FileInfo;
use JchOptimize\Core\FileUtils;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Html\Elements\Link;
use JchOptimize\Core\Html\Elements\Script;
use JchOptimize\Core\Html\Elements\Style;
use JchOptimize\Core\Platform\ExcludesInterface;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Registry;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\Uri;
use JchOptimize\Core\Uri\UriComparator;
use SplObjectStorage;

use function array_pad;
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

    /** @var array $aExcludedJs Javascript matches that will be excluded.
     *        Will be moved to the bottom of section if not selected in "don't move"
     */
    public array $aExcludedJs = [];

    /**
     * @var int $jsExcludedIndex Recorded incremented index of js files when the last file was excluded
     */
    public int $jsExcludedIndex = 0;

    /**
     * @var SplObjectStorage<Script, mixed>
     */
    public SplObjectStorage $deferredScriptStorage;


    protected ?HtmlElementInterface $element = null;

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
     * } $aExcludes Multidimensional array of excludes set in the parameters.
     */
    public array $aExcludes = [
        'excludes_peo' => [
            'js' => [[]],
            'css' => [],
            'js_script' => [[]],
            'css_script' => []
        ],
        'critical_js' => [
            'js' => [],
            'script' => [],
        ],
        'remove' => [
            'js' => [],
            'css' => []
        ]
    ];

    /**
     * @var array $aMatches Array of matched elements holding links to CSS/Js files on the page
     */
    protected array $aMatches = [];

    /**
     * @var array $cssReplacements Array of CSS matches to be removed
     */
    public array $cssReplacements = [[]];

    /**
     * @var array $jsReplacements Array of JavaScript matched to be removed
     */
    public array $jsReplacements = [[]];

    /**
     * @var array Marks the place where combined JavaScript files will be placed in the HTML for the
     *            indicated index
     */
    public array $jsMarker = [];

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
    private array $smartCombinePreviousParts = [
        'js' => null,
        'css' => null
    ];
    /**
     * @var array|int[]
     */
    private array $smartCombineCounters = [
        'js' => 0,
        'css' => 0
    ];

    /**
     * Private constructor, need to implement a singleton of this class
     */
    public function __construct(
        private Registry $params,
        private FileUtils $fileUtils,
        private ExcludesInterface $excludes,
        private ?ClientInterface $http
    ) {
        $this->deferredScriptStorage = new SplObjectStorage();
    }

    public function setExcludes(array $aExcludes): void
    {
        $this->aExcludes = $aExcludes;
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
            $this->excludeJsIEO();
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

        if ($this->isDuplicated($uri)) {
            $this->replacement = '';
            $this->excludeCssIEO();
        }

        //process excludes for css urls
        if (
            $this->excludeGenericUrls($uri)
            || Helper::findExcludes(@$this->aExcludes['excludes_peo']['css'], (string)$uri)
        ) {
            //If Optimize CSS Delivery enabled, always exclude IEO
            if ($this->params->get('optimizeCssDelivery_enable', '0')) {
                $this->excludeCssIEO();
            } else {
                $this->excludeCssPEO();
            }
        }

        $this->updateIndex();

        //File was not excluded
        $this->cssExcludedPeo = false;
        $this->cssExcludedIeo = false;
        //Record file info for download
        $this->aCss[$this->iIndex_css][] = new FileInfo(clone $link);
        //Record match to be replaced
        $this->cssReplacements[$this->iIndex_css][] = $link;
    }

    private function getMediaAttribute(): string
    {
        return (string) ($this->getElement()->attributeValue('media') ?: '');
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
                && preg_match('#' . $this->excludes->extensions() . '#i', (string)$uri)
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
    private function excludeCssPEO()
    {
        //if previous file was not excluded increment css index
        if (!$this->cssExcludedPeo && !empty($this->cssReplacements[0])) {
            $this->iIndex_css++;
        }

        $this->cssExcludedPeo = true;
        $this->sCssExcludeType = 'peo';

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
        if (!$this->params->get('combine_files', '0')) {
            $type = ($this->getElement() instanceof Script) ? 'js' : 'css';

            if ($type == 'js') {
                //Don't increase index if we're in an exclude. Index already incremented
                if (!$this->jsExcludedPeo && !empty($this->aJs[0])) {
                    $this->iIndex_js++;
                }
                //Don't increase index if we're in an exclude. Index already incremented
            } elseif (!$this->cssExcludedPeo && !empty($this->aCss[0])) {
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

        if (
            Helper::findExcludes(@$this->aExcludes['excludes_peo']['css_script'], Css::optimize($content), 'css')
            || !$this->params->get('inlineStyle', '0')
            || $this->params->get('excludeAllStyles', '0')
        ) {
            if ($this->params->get('optimizeCssDelivery_enable', '0')) {
                $this->excludeCssIEO();
            } else {
                $this->excludeCssPEO();
            }
        }

        $this->updateIndex();
        $this->cssExcludedPeo = false;
        $this->cssExcludedIeo = false;

        $this->aCss[$this->iIndex_css][] = new FileInfo(clone $style);
        $this->cssReplacements[$this->iIndex_css][] = $style;
    }

    /**
     * @throws ExcludeException
     */
    private function processJsUrl(Script $script): void
    {
        $uri = $script->getSrc();

        if (!$uri instanceof UriInterface) {
            $this->excludeJsIEO();
        }

        if ($this->isDuplicated($uri)) {
            $this->replacement = '';
            $this->excludeJsIEO(false);
        }

        foreach ($this->aExcludes['excludes_peo']['js'] as $exclude) {
            if (!empty($exclude['url']) && Helper::findExcludes([$exclude['url']], (string)$uri)) {
                //If dont move, don't add to excludes
                $addToExcludes = !isset($exclude['dontmove']);
                //Handle js files IEO
                if (isset($exclude['ieo']) || Helper::isScriptDeferred($script)) {
                    $this->excludeJsIEO($addToExcludes);
                } else {
                    //Prepare PEO excludes for js urls
                    $this->excludeJsPEO($addToExcludes);
                }
            }
        }

        //This is placed below exclusions because sometimes we want to be able to prevent deferred files from
        //moving to the bottom.
        if (Helper::isScriptDeferred($script)) {
            $this->deferredScriptStorage->attach($script);

            $this->excludeJsIEO(false);
        }

        if ($this->excludeGenericUrls($uri)) {
            $this->excludeJsPEO();
        }

        $this->updateIndex();
        $this->responseToPreviousExclude();
        $this->jsExcludedPeo = false;
        $this->jsExcludedIeo = false;

        $this->aJs[$this->iIndex_js][] = new FileInfo(clone $script);

        $this->jsReplacements[$this->iIndex_js][] = $script;
    }

    /**
     * @return never
     * @throws ExcludeException
     */
    public function excludeJsIEO($addToExcludes = true)
    {
        $this->jsExcludedIeo = true;
        $this->sJsExcludeType = 'ieo';

        if ($addToExcludes) {
            $this->aExcludedJs[] = $this->getElement();
        }

        throw new ExcludeException();
    }

    /**
     * @return never
     * @throws ExcludeException
     */
    private function excludeJsPEO($addToExcludes = true)
    {
        //If previous file was not excluded, update marker
        if (!$this->jsExcludedPeo) {
            $marker = $this->getElement()->data('jch', 'js' . $this->iIndex_js);

            $this->jsMarker = array_pad($this->jsMarker, ($this->iIndex_js + 1), $marker);
        }

        if ($addToExcludes) {
            $this->aExcludedJs[] = $this->getElement();
        }

        //Record index of last excluded file
        $this->jsExcludedIndex = $this->iIndex_js;

        $this->jsExcludedPeo = true;
        $this->sJsExcludeType = 'peo';

        throw new ExcludeException();
    }

    /**
     * @throws ExcludeException
     */
    private function processJsContent(Script $script): void
    {
        $content = $script->getChildren()[0];

        foreach ($this->aExcludes['excludes_peo']['js_script'] as $exclude) {
            if (!empty($exclude['script']) && Helper::findExcludes([$exclude['script']], Js::optimize($content))) {
                //If 'dontmove', don't add to excludes
                $addToExcludes = !isset($exclude['dontmove']);

                if (isset($exclude['ieo'])) {
                    //process IEO excludes for js scripts
                    $this->excludeJsIEO($addToExcludes);
                } else {
                    //Prepare PEO excludes for js scripts
                    $this->excludeJsPEO($addToExcludes);
                }
            }
        }

        //Exclude all scripts if options set
        if (
            !$this->params->get('inlineScripts', '0')
            || $this->params->get('excludeAllScripts', '0')
        ) {
            $this->excludeJsPEO();
        }

        if (Helper::isScriptDeferred($script)) {
            $this->deferredScriptStorage->attach($script);
            $this->excludeJsIEO(false);
        }

        $this->updateIndex();
        $this->responseToPreviousExclude();
        $this->jsExcludedPeo = false;
        $this->jsExcludedIeo = false;

        $this->aJs[$this->iIndex_js][] = new FileInfo(clone $script);
        $this->jsReplacements[$this->iIndex_js][] = $script;
    }

    private function responseToPreviousExclude(): void
    {
        //If previous file was excluded PEO, update index
        if ($this->jsExcludedPeo) {
            $this->iIndex_js++;
        }
    }

    private function mediaValueWillChangeOnLoad(Link $link): bool
    {
        return str_contains((string)$link->attributeValue('onload'), 'media');
    }
}
