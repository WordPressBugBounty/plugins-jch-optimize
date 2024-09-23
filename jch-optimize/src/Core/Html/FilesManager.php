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

use CodeAlfa\Minify\Html;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use JchOptimize\Core\Exception\ExcludeException;
use JchOptimize\Core\FeatureHelpers\Fonts;
use JchOptimize\Core\FileUtils;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Http2Preload;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\UriComparator;
use JchOptimize\Platform\Excludes;
use Joomla\DI\ContainerAwareInterface;
use Joomla\DI\ContainerAwareTrait;
use Joomla\Registry\Registry;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\UriInterface;

use function array_map;
use function array_merge;
use function defined;
use function extension_loaded;
use function implode;
use function in_array;
use function is_null;
use function json_decode;
use function preg_match;
use function preg_replace;
use function rawurldecode;
use function ucfirst;

defined('_JCH_EXEC') or die('Restricted access');

/**
 * Handles the exclusion and replacement of files in the HTML based on set parameters
 */
class FilesManager implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @var bool $bLoadAsync Indicates if we can load the last javascript files asynchronously
     */
    public bool $bLoadJsAsync = true;

    /**
     * @var bool Flagged anytime JavaScript files are excluded PEI
     */
    public bool $jsFilesExcludedPei = false;

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
    public array $aExcludedJs = [
        'ieo' => [],
        'peo' => []
    ];

    /**
     * @var int $jsExcludedIndex Recorded incremented index of js files when the last file was excluded
     */
    public int $jsExcludedIndex = 0;

    /**
     * @var array $defers Javascript files having the defer attribute
     */
    public array $defers = [];

    /**
     * @var string[] $aMatch Current match being worked on
     */
    public array $aMatch = [];

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
     * }   $aExcludes Multidimensional array of excludes set in the parameters.
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
     * @var Registry $params
     */
    private Registry $params;

    /**
     * @var string $type Type of file being processed (css|js)
     */
    protected string $type = '';

    /**
     * @var array $aMatches Array of matched elements holding links to CSS/Js files on the page
     */
    protected array $aMatches = [];

    /**
     * @var int $iIndex Current index of matches
     */
    protected int $iIndex = -1;

    /**
     * @var array $aReplacements Array of replacements of matched links
     */
    protected array $aReplacements = [];

    /**
     * @var string $replacement String to replace the matched link
     */
    protected string $replacement = '';

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
     * @var ClientInterface|null
     */
    private ?ClientInterface $http;
    /**
     * @var Http2Preload
     */
    private Http2Preload $http2Preload;
    /**
     * @var FileUtils
     */
    private FileUtils $fileUtils;
    /**
     * @var string Previous match of a script with module/async/defer attribute
     */
    private static string $prevDeferMatches = '';
    /**
     * @var int Current index of the defers array
     */
    private static int $deferIndex = -1;

    /**
     * Private constructor, need to implement a singleton of this class
     */
    public function __construct(
        Registry         $params,
        Http2Preload     $http2Preload,
        FileUtils        $fileUtils,
        ?ClientInterface $http
    ) {
        $this->params = $params;
        $this->http2Preload = $http2Preload;
        $this->fileUtils = $fileUtils;
        $this->http = $http;
    }

    public function setExcludes(array $aExcludes): void
    {
        $this->aExcludes = $aExcludes;
    }

    /**
     * @param string $type
     * @param array $aMatch
     *
     * @return string
     */
    public function processFiles(string $type, array $aMatch): string
    {
        $this->aMatch = $aMatch;
        $this->type = $type;
        $this->iIndex++;
        $this->aMatches[$this->iIndex] = $aMatch[0];
        //Initialize replacement
        $this->replacement = '';

        try {
            if (isset($aMatch['url'])) {
                $this->checkUrls($aMatch['url']);
                /**
                 * @see FilesManager::processJsUrl()
                 * @see FilesManager::processCssUrl()
                 */
                $this->{'process' . ucfirst($type) . 'Url'}($aMatch['url']);
            } elseif (isset($aMatch['content'])) {
                /**
                 * @see FilesManager::processJsContent()
                 * @see FilesManager::processCssContent()
                 */
                $this->{'process' . ucfirst($type) . 'Content'}($aMatch['content']);
            }
        } catch (ExcludeException $e) {
        }

        return $this->replacement;
    }

    private function checkUrls(UriInterface $uri): void
    {
        //Exclude invalid urls
        if ($uri->getScheme() == 'data') {
            $this->{'exclude' . ucfirst($this->type) . 'IEO'}();
        }
    }

    /**
     * @throws ExcludeException
     */
    private function processCssUrl(UriInterface $uri): void
    {
        //Get media value if attribute set
        $sMedia = $this->getMediaAttribute();

        //process google font files or other CSS files added to be optimized
        if ($uri->getHost() == 'fonts.googleapis.com' || Helper::findExcludes(
            Helper::getArray($this->params->get('pro_optimize_font_files', [])),
            (string)$uri
        )) {
            if (JCH_PRO) {
                /** @see Fonts::pushFileToFontsArray() */
                $this->container->get(Fonts::class)->pushFileToFontsArray($uri, $sMedia);
            }

            //if Optimize Fonts not enabled just return Google Font files. Google fonts will serve a different version
            //for different browsers and creates problems when we try to cache it.
            if ($uri->getHost() == 'fonts.googleapis.com' && !$this->params->get('pro_optimizeFonts_enable', '0')) {
                $this->replacement = $this->aMatch[0];
            }

            $this->excludeCssIEO();
        }

        if ($this->isDuplicated($uri)) {
            $this->excludeCssIEO();
        }

        //process excludes for css urls
        if ($this->excludeGenericUrls($uri)
            || Helper::findExcludes(@$this->aExcludes['excludes_peo']['css'], (string)$uri)) {
            $this->excludeCssPEO();
        }

        $this->prepareCssPEO();
        $this->processSmartCombine($uri);

        $this->aCss[$this->iIndex_css][] = [
            'url' => $uri,
            'media' => $sMedia,
        ];
    }

    private function getMediaAttribute(): string
    {
        $sMedia = '';

        if ((preg_match('#media=(?(?=["\'])(?:["\']([^"\']+))|(\w+))#i', $this->aMatch[0], $aMediaTypes) > 0)) {
            $sMedia .= $aMediaTypes[1] ?: $aMediaTypes[2];
        }

        return $sMedia;
    }

    /**
     * @return never
     * @throws ExcludeException
     *
     */
    private function excludeCssIEO()
    {
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

        //Exclude files from external extensions if parameter not set (PEO)
        if (!$this->params->get('includeAllExtensions', '0')) {
            if (!UriComparator::isCrossOrigin($resolvedUri)
                && preg_match('#' . Excludes::extensions() . '#i', (string)$uri)) {
                return true;
            }
        }

        //Exclude all external and dynamic files
        if (!$this->params->get('phpAndExternal', '0')) {
            if (UriComparator::isCrossOrigin($resolvedUri)
                || !Helper::isStaticFile($uri->getPath())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines if the given url requires an http wrapper to fetch it and if an http adapter is available.
     *
     * @param UriInterface $uri
     *
     * @return bool
     */
    public function isHttpAdapterAvailable(UriInterface $uri): bool
    {
        return !is_null($this->http);
    }

    public function isPHPFile(string $url): bool
    {
        return (bool)preg_match('#\.php|^(?![^?\#]*\.(?:css|js|png|jpe?g|gif|bmp)(?:[?\#]|$)).++#i', $url);
    }

    /**
     * @return never
     * @throws ExcludeException
     */
    private function excludeCssPEO()
    {
        //if previous file was excluded increment css index
        if (!empty($this->aCss[$this->iIndex_css]) && !$this->params->get('optimizeCssDelivery_enable', '0')) {
            $this->iIndex_css++;
        }

        //Just return the match at same location
        $this->replacement = $this->aMatch[0];
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

    private function prepareCssPEO(): void
    {
        //return marker for combined file
        if (empty($this->aCss[$this->iIndex_css]) && !$this->params->get('optimizeCssDelivery_enable', '0')) {
            $this->replacement = '<JCH_CSS' . $this->iIndex_css . '>';
        }
    }

    private function processSmartCombine(UriInterface $uri): void
    {
        if ($this->params->get('pro_smart_combine', '0')) {
            $sType = $this->type;

            $aSmartCombineValues = $this->params->get('pro_smart_combine_values', '');
            $aSmartCombineValues = $aSmartCombineValues != '' ? json_decode(rawurldecode($aSmartCombineValues)) : [];

            //Index of files currently being smart combined
            static $iSmartCombineIndex_js = false;
            static $iSmartCombineIndex_css = false;

            $sBaseUrl = Uri::composeComponents($uri->getScheme(), $uri->getAuthority(), $uri->getPath(), '', '');

            foreach (Excludes::smartCombine() as $iIndex => $sRegex) {
                if (preg_match('#' . $sRegex . '#i', (string)$uri) && in_array(
                    $sBaseUrl,
                    $aSmartCombineValues
                )) {//We're in a batch
                    //Is this the first file in this batch?
                    if (!empty(
                        $this->{'a' . ucfirst(
                            $sType
                        )}[$this->{'iIndex_' . $sType}]
                    ) && ${'iSmartCombineIndex_' . $sType} !== $iIndex) {
                        $this->{'iIndex_' . $sType}++;

                        if ($sType == 'css' && $this->replacement == '' && !$this->params->get(
                            'optimizeCssDelivery_enable',
                            '0'
                        )) {
                            $this->replacement = '<JCH_CSS' . $this->iIndex_css . '>';
                        }
                    }

                    if ($sType == 'js') {
                        $this->bLoadJsAsync = false;
                    }

                    //Save index
                    ${'iSmartCombineIndex_' . $sType} = $iIndex;

                    break;
                } elseif (${'iSmartCombineIndex_' . $sType} === $iIndex) {
                    //Have we just finished a batch?
                    ${'iSmartCombineIndex_' . $sType} = false;

                    if (!empty($this->{'a' . ucfirst($sType)}[$this->{'iIndex_' . $sType}])) {
                        $this->{'iIndex_' . $sType}++;

                        if ($sType == 'css' && $this->replacement == '' && !$this->params->get(
                            'optimizeCssDelivery_enable',
                            '0'
                        )) {
                            $this->replacement = '<JCH_CSS' . $this->iIndex_css . '>';
                        }
                    }
                }
            }
        }
    }

    /**
     * @throws ExcludeException
     */
    private function processCssContent(string $content): void
    {
        $media = $this->getMediaAttribute();

        if (Helper::findExcludes(@$this->aExcludes['excludes_peo']['css_script'], $content, 'css')
            || !$this->params->get('inlineStyle', '0')
            || $this->params->get('excludeAllStyles', '0')) {
            $this->excludeCssPEO();
        }

        $this->prepareCssPEO();

        $this->aCss[$this->iIndex_css][] = [
            'content' => Html::cleanScript($content, 'css'),
            'media' => $media,
        ];
    }

    /**
     * @throws ExcludeException
     */
    private function processJsUrl(UriInterface $uri): void
    {
        if ($this->isDuplicated($uri)) {
            $this->excludeJsIEO();
        }

        foreach ($this->aExcludes['excludes_peo']['js'] as $exclude) {
            if (!empty($exclude['url']) && Helper::findExcludes([$exclude['url']], (string)$uri)) {
                //Handle js files PEO
                if (!isset($exclude['ieo'])) {
                    $this->http2Preload->add($uri, 'js');

                    //prepare js match for excluding PEO
                    $this->prepareJsPEO();

                    //Return match if selected as "don't move"
                    if (isset($exclude['dontmove'])) {
                        //Need to make sure execution order is maintained
                        $this->prepareJsDontMoveReplacement();
                    } else {
                        $this->aExcludedJs['peo'][] = $this->aMatch[0];
                    }

                    $this->excludeJsPEO();
                    //Prepare IEO excludes for js urls
                } else {
                    $deferred = $this->isFileDeferred($this->aMatch[0]);
                    $this->http2Preload->add($uri, 'js', $deferred);

                    //Return match if selected as "don't move"
                    if (isset($exclude['dontmove'])) {
                        $this->replacement = $this->aMatch[0];
                        //Else add to array of excluded js files
                    } else {
                        $this->aExcludedJs['ieo'][] = $this->aMatch[0];
                    }

                    $this->excludeJsIEO();
                }
            }
        }

        //Add all defers, modules and nomodules to the defer array, incrementing the index each time a
        // different type is encountered
        if ($this->hasAttributes($this->aMatch[0], ['type=module', 'nomodule'], $matches)
            || $this->hasAttributes($this->aMatch[0], ['async'], $matches)
            || $this->hasAttributes($this->aMatch[0], ['defer'], $matches)) {
            if ($matches[1] != self::$prevDeferMatches) {
                self::$deferIndex++;
                self::$prevDeferMatches = $matches[1];
            }

            $this->defers[self::$deferIndex][] = [
                'attribute' => $matches[0],
                'attributeType' => $matches[1],
                'script' => $this->aMatch[0],
                'url' => $uri
            ];

            $this->bLoadJsAsync = false;

            $this->excludeJsIEO();
        }

        if ($this->excludeGenericUrls($uri)) {
            $this->prepareJsPEO();
            $this->aExcludedJs['peo'][] = $this->aMatch[0];
            $this->excludeJsPEO();
        }

        $this->processSmartCombine($uri);

        $this->aJs[$this->iIndex_js][] = [
            'url' => $uri,
        ];
    }

    /**
     * @return never
     * @throws ExcludeException
     */
    public function excludeJsIEO()
    {
        $this->sJsExcludeType = 'ieo';

        throw new ExcludeException();
    }

    public function isFileDeferred(string $script, bool $bIgnoreAsync = false): bool
    {
        //File is deferred if it has async or defer attributes
        $attributes = ['defer'];

        if (!$bIgnoreAsync) {
            $attributes = array_merge($attributes, ['async']);
        }

        return $this->hasAttributes($script, $attributes, $matches);
    }

    public static function hasAttributes(string $element, array $attributes, ?array &$matches): bool
    {
        $a = Parser::htmlAttributeWithCaptureValueToken();
        $attrRegex = implode(
            '|',
            array_map(function ($a) {
                $b = preg_replace('#=(.*)#', '\s*=\s*(?|"(\1)"|\'(\1)\'|(\1))', $a, 1, $count);

                if (!$count) {
                    return '(' . $a . ')(?:\s*=\s*(?|"[^"]*+"|\'[^\']*+\'|[^\s/>]*+))?';
                }

                return $b;
            }, $attributes)
        );

        $regex = "#<\w++\b(?>\s*+{$a})*?\s*+\K(?|$attrRegex)#i";

        return (bool) preg_match($regex, $element, $matches);
    }

    private function prepareJsPEO(): void
    {
        //If files were previously added for combine in the current index
        // then place marker for combined file(s) above match marked for exclude
        if (!empty($this->aJs[$this->iIndex_js])) {
            $jsReturn = '';

            for ($i = $this->jsExcludedIndex; $i <= $this->iIndex_js; $i++) {
                $jsReturn .= '<JCH_JS' . $i . '>' . "\n\t";
            }

            $this->aMatch[0] = $jsReturn . $this->aMatch[0];

            //increment index of combined files and record it
            $this->jsExcludedIndex = ++$this->iIndex_js;
        }
    }

    private function prepareJsDontMoveReplacement(): void
    {
        //We'll need to put all the PEO excluded files above this one
        $this->aMatch[0] = implode("\n", $this->aExcludedJs['peo']) . "\n" . $this->aMatch[0];
        $this->replacement = $this->aMatch[0];
        //reinitialize array of PEO excludes
        $this->aExcludedJs['peo'] = [];
    }

    /**
     * @return never
     * @throws ExcludeException
     */
    private function excludeJsPEO()
    {
        //Can no longer load last combined file asynchronously
        $this->bLoadJsAsync = false;
        $this->jsFilesExcludedPei = true;
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
                //process PEO excludes for js scripts
                if (!isset($exclude['ieo'])) {
                    $this->prepareJsPEO();

                    //Return match if selected as don't move
                    if (isset($exclude['dontmove'])) {
                        //Need to make sure execution order is maintained
                        $this->prepareJsDontMoveReplacement();
                        //Else add to array of excluded js
                    } else {
                        $this->aExcludedJs['peo'][] = $this->aMatch[0];
                    }

                    $this->excludeJsPEO();
                    //Prepare IEO excludes for js scripts
                } else {
                    //Return match if select as don't move
                    if (isset($exclude['dontmove'])) {
                        $this->replacement = $this->aMatch[0];
                        //Else add to array of excluded js
                    } else {
                        $this->aExcludedJs['ieo'][] = $this->aMatch[0];
                    }

                    $this->excludeJsIEO();
                }
            }
        }

        //Exclude all scripts if options set
        if (!$this->params->get('inlineScripts', '0') || $this->params->get('excludeAllScripts', '0')) {
            $this->prepareJsPEO();
            $this->aExcludedJs['peo'][] = $this->aMatch[0];
            $this->excludeJsPEO();
        }

        //Add all modules and nomodules to the defer array, incrementing the index each time a
        // different type is encountered. The defer and async attribute on inline scripts are ignored
        if ($this->hasAttributes($this->aMatch[0], ['type=module', 'nomodule'], $matches)) {
            if ($matches[1] != self::$prevDeferMatches) {
                self::$deferIndex++;
                self::$prevDeferMatches = $matches[1];
            }

            $this->defers[self::$deferIndex][] = [
                'attribute' => $matches[0],
                'attributeType' => $matches[1],
                'script' => $this->aMatch[0],
                'content' => $content
            ];

            $this->bLoadJsAsync = false;

            $this->excludeJsIEO();
        }

        $this->aJs[$this->iIndex_js][] = [
            'content' => Html::cleanScript($content, 'js'),
        ];
    }
}
