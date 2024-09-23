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

namespace JchOptimize\Core;

use CodeAlfa\Minify\Css;
use CodeAlfa\Minify\Js;
use CodeAlfa\RegexTokenizer\Debug\Debug;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\RequestOptions;
use JchOptimize\Core\Css\Processor as CssProcessor;
use JchOptimize\Core\Css\Sprite\Generator;
use JchOptimize\Core\Uri\UriComparator;
use JchOptimize\Core\Uri\UriConverter;
use JchOptimize\Platform\Profiler;
use Joomla\DI\ContainerAwareInterface;
use Joomla\DI\ContainerAwareTrait;
use Joomla\Registry\Registry;
use Laminas\Cache\Pattern\CallbackCache;
use Laminas\Cache\Storage\IterableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Cache\Storage\TaggableInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Serializable;

use function array_merge;
use function array_unique;
use function defined;
use function file_exists;
use function md5;
use function preg_last_error;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_replace;
use function substr;
use function time;

defined('_JCH_EXEC') or die('Restricted access');

/**
 * Class to combine CSS/JS files together
 */
class Combiner implements ContainerAwareInterface, LoggerAwareInterface, Serializable
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;
    use Debug;
    use FileInfosUtilsTrait;
    use SerializableTrait;
    use StorageTaggingTrait;

    /**
     * @var bool
     */
    public bool $isBackend;

    /**
     * @var Registry
     */
    private Registry $params;

    /**
     * @var CallbackCache $callbackCache
     */
    private CallbackCache $callbackCache;

    /**
     * @var (Client&ClientInterface)|null
     */
    private $http;

    /**
     * @var StorageInterface&TaggableInterface&IterableInterface
     */
    private $taggableCache;

    /**
     * Constructor
     *
     * @param   Registry                                              $params
     * @param   CallbackCache                                         $callbackCache
     * @param   StorageInterface&TaggableInterface&IterableInterface  $taggableCache
     * @param   FileUtils                                             $fileUtils
     * @param   (Client&ClientInterface)|null                         $http
     * @param   bool                                                  $isBackend
     */
    public function __construct(
        Registry $params,
        CallbackCache $callbackCache,
        $taggableCache,
        FileUtils $fileUtils,
        $http,
        bool $isBackend = false
    ) {
        $this->params = $params;
        $this->callbackCache = $callbackCache;
        $this->taggableCache = $taggableCache;
        $this->fileUtils = $fileUtils;
        $this->http = $http;
        $this->isBackend = $isBackend;
    }

    public function getCssContents(array $urlArray): array
    {
        return $this->getContents($urlArray, 'css');
    }

    /**
     * Get aggregated and possibly minified content from js and css files
     *
     * @param   array   $urlArray  Indexed multidimensional array of urls of css or js files for aggregation
     * @param   string  $type      css or js
     *
     * @return array   Aggregated (and possibly minified) contents of files
     * @throws Exception
     */
    public function getContents(array $urlArray, string $type): array
    {
        !JCH_DEBUG ?: Profiler::start('GetContents - ' . $type, true);

        $aResult = $this->combineFiles($urlArray, $type);
        $sContents = $this->prepareContents($aResult['content']);

        if ($type == 'css') {
            if ($this->params->get('csg_enable', 0)) {
                try {
                    /** @var Generator $oSpriteGenerator */
                    $oSpriteGenerator = $this->container->get(Generator::class);

                    $aSpriteCss = $oSpriteGenerator->getSprite($sContents);

                    if (!empty($aSpriteCss) && !empty($aSpriteCss['needles']) && !empty($aSpriteCss['replacements'])) {
                        $sContents = str_replace($aSpriteCss['needles'], $aSpriteCss['replacements'], $sContents);
                    }
                } catch (Exception $ex) {
                    $this->logger->error($ex->getMessage());
                }
            }

            $sContents = $aResult['import'] . $sContents;

            if (function_exists('mb_convert_encoding')) {
                $sContents = '@charset "utf-8";' . $sContents;
            }
        }

        //Save contents in array to store in cache
        $aContents = [
            'filemtime'   => time(),
            'etag'        => md5($sContents),
            'contents'    => $sContents,
            'images'      => array_unique($aResult['images']),
            'font-face'   => $aResult['font-face'],
            'preconnects' => $aResult['preconnects'],
            'gfonts'      => $aResult['gfonts'],
            'bgselectors' => $aResult['bgselectors']
        ];

        !JCH_DEBUG ?: Profiler::stop('GetContents - ' . $type);

        return $aContents;
    }

    /**
     * Aggregate contents of CSS and JS files
     *
     * @param   array   $fileInfosArray  Array of links of files to combine
     * @param   string  $type            css|js
     *
     * @return array               Aggregated contents
     * @throws Exception
     */
    public function combineFiles(array $fileInfosArray, string $type, $cacheItems = true): array
    {
        $responses = [
            'content'     => '',
            'import'      => '',
            'font-face'   => [],
            'preconnects' => [],
            'images'      => [],
            'gfonts'      => [],
            'bgselectors' => []
        ];

        //Iterate through each file/script to optimize and combine
        foreach ($fileInfosArray as $fileInfos) {
            //Truncate url to less than 40 characters
            $sUrl = $this->prepareFileUrl($fileInfos, $type);

            !JCH_DEBUG ?: Profiler::start('CombineFile - ' . $sUrl);

            //Try to store tags first
            $function = [$this, 'cacheContent'];
            $args = [$fileInfos, $type, true];
            $id = $this->callbackCache->generateKey($function, $args);
            $this->tagStorage($id);

            //If caching set and tagging was successful we attempt to cache
            if ($cacheItems && !empty($this->taggableCache->getTags($id))) {
                //Optimize and cache file/script returning the optimized content
                $results = $this->callbackCache->call($function, $args);

                //Append to combined contents
                $responses['content'] .= $this->addCommentedUrl(
                    $type,
                    $fileInfos
                ) . $results['content'] . "\n" . 'JCHOPTIMIZE_DELIMITER';
            } else {
                //If we're not caching just get the optimized content
                $results = $this->cacheContent($fileInfos, $type, false);
                $responses['content'] .= $this->addCommentedUrl(
                    $type,
                    $fileInfos
                ) . $results['content'] . '|"JCHOPTIMIZE_LINE_END"|';
            }

            if ($type == 'css') {
                $responses['import'] .= $results['import'];
                $responses['images'] = array_merge($responses['images'], $results['images']);
                $responses['gfonts'] = array_merge($responses['gfonts'], $results['gfonts']);
                $responses['font-face'] = array_merge($responses['font-face'], $results['font-face']);
                $responses['preconnects'] = array_merge($responses['preconnects'], $results['preconnects']);
                $responses['bgselectors'] = array_merge($responses['bgselectors'], $results['bgselectors']);
            }

            !JCH_DEBUG ?: Profiler::stop('CombineFile - ' . $sUrl, true);
        }

        return $responses;
    }

    /**
     *
     * @param   string  $type
     * @param   array   $fileInfos
     *
     * @return string
     */
    protected function addCommentedUrl(string $type, array $fileInfos): string
    {
        $comment = '';

        if ($this->params->get('debug', '1')) {
            $fileInfos = $fileInfos['url'] ?? (($type == 'js' ? 'script' : 'style') . ' declaration');

            $comment = '|"JCHOPTIMIZE_COMMENT_START ' . $fileInfos . ' JCHOPTIMIZE_COMMENT_END"|';
        }

        return $comment;
    }

    /**
     * Optimize and cache contents of individual file/script returning optimized content
     *
     * @param   array   $fileInfos
     * @param   string  $type
     * @param   bool    $bPrepare
     *
     * @return array
     */
    public function cacheContent(array $fileInfos, string $type, bool $bPrepare): array
    {
        //Initialize content string
        $content = '';
        $responses = [];

        //If it's a file fetch the contents of the file
        if (isset($fileInfos['url'])) {
            $content .= $this->getFileContents($fileInfos['url']);

            //Remove zero-width non-breaking space
            $content = trim($content, "\xEF\xBB\xBF");
        } else {
            //If it's a declaration just use it
            $content .= $fileInfos['content'];
        }

        if ($type == 'css') {
            /** @var CssProcessor $oCssProcessor */
            $oCssProcessor = $this->container->get(CssProcessor::class);
            $oCssProcessor->setCssInfos($fileInfos);
            $oCssProcessor->setCss($content);
            $oCssProcessor->formatCss();
            $oCssProcessor->processUrls(false, false, $this->isBackend);
            $oCssProcessor->processMediaQueries();
            $oCssProcessor->processAtRules();

            $content = $oCssProcessor->getCss();

            $responses['import'] = $oCssProcessor->getImports();
            $responses['images'] = $oCssProcessor->getImages();
            $responses['font-face'] = $oCssProcessor->getFontFace();
            $responses['gfonts'] = $oCssProcessor->getGFonts();
            $responses['preconnects'] = $oCssProcessor->getPreconnects();
            $responses['bgselectors'] = $oCssProcessor->getCssBgImagesSelectors();
        }

        if ($type == 'js' && trim($content) != '') {
            if ($this->params->get('try_catch', '1')) {
                $content = $this->addErrorHandler($content, $fileInfos);
            } else {
                $content = $this->addSemiColon($content);
            }
        }

        if ($bPrepare) {
            $content = $this->minifyContent($content, $type, $fileInfos);
            $content = $this->prepareContents($content);
        }

        $responses['content'] = $content;

        return $responses;
    }

    private function getFileContents(UriInterface $uri): string
    {
        $uri = UriResolver::resolve(SystemUri::currentUri(), $uri);

        if (!UriComparator::isCrossOrigin($uri)) {
            $filePath = UriConverter::uriToFilePath($uri);

            if (file_exists($filePath) && Helper::isStaticFile($filePath)) {
                try {
                    $stream = Utils::streamFor(Utils::tryFopen($filePath, 'r'));

                    if (!$stream->isReadable()) {
                        throw new Exception('Stream unreadable');
                    }

                    if ($stream->isSeekable()) {
                        $stream->rewind();
                    }

                    return $stream->getContents();
                } catch (Exception $e) {
                    $this->logger->warning('Couldn\'t open file: ' . $uri . '; error: ' . $e->getMessage());
                }
            }
        }

        try {
            $options = [
                RequestOptions::HEADERS => [
                    'Accept-Enconding' => 'identity;q=0'
                ]
            ];

            $response = $this->http->get($uri, $options);
            if ($response->getStatusCode() === 200) {
                //Get body and set pointer to beginning of stream
                $body = $response->getBody();
                $body->rewind();

                return $body->getContents();
            } else {
                return '|"JCHOPTIMIZE_COMMENT_START Response returned status code: ' . $response->getStatusCode(
                ) . ' JCHOPTIMIZE_COMMENT_END"|';
            }
        } catch (GuzzleException $e) {
            return '|"JCHOPTIMIZE_COMMENT_START Exception fetching file with message: ' . $e->getMessage(
            ) . ' JCHOPTIMIZE_COMMENT_END"|';
        }
    }

    /**
     * Add try catch to contents of javascript file
     *
     * @param   string  $content
     * @param   array   $fileInfos
     *
     * @return string
     */
    private function addErrorHandler(string $content, array $fileInfos): string
    {
        if (empty($fileInfos['module']) || $fileInfos['module'] != 'module') {
            $content = 'try {' . "\n" . $content . "\n" . '} catch (e) {' . "\n";
            $content .= 'console.error(\'Error in ';
            $content .= isset($fileInfos['url']) ? 'file:' . $fileInfos['url'] : 'script declaration';
            $content .= '; Error:\' + e.message);' . "\n" . '};';
        }

        return $content;
    }

    /**
     * Add semicolon to end of js files if non exists;
     *
     * @param   string  $content
     *
     * @return string
     */
    private function addSemiColon(string $content): string
    {
        $content = rtrim($content);

        if (substr($content, -1) != ';' && !preg_match(
            '#\|"JCHOPTIMIZE_COMMENT_START File[^"]+not found JCHOPTIMIZE_COMMENT_END"\|#',
            $content
        )) {
            $content = $content . ';';
        }

        return $content;
    }

    /**
     * Minify contents of fil
     *
     * @param   string  $content
     * @param   string  $type
     * @param   array   $fileInfos
     *
     * @return string $sMinifiedContent Minified content or original content if failed
     */
    private function minifyContent(string $content, string $type, array $fileInfos): string
    {
        if ($this->params->get($type . '_minify', 0)) {
            $url = $this->prepareFileUrl($fileInfos, $type);

            $minifiedContent = trim($type == 'css' ? Css::optimize($content) : Js::optimize($content));

            /* @TODO inject Exception class into minifier libraries */
            if (preg_last_error() !== 0) {
                $this->logger->error(sprintf('Error occurred trying to minify: %s', $url));
                $minifiedContent = $content;
            }

            $this->_debug($url, '', 'minifyContent');

            return $minifiedContent;
        }

        return $content;
    }

    /**
     * Remove placeholders from aggregated file for caching
     *
     * @param   string  $contents  Aggregated file contents
     * @param   bool    $test
     *
     * @return string
     */
    public function prepareContents(string $contents, bool $test = false): string
    {
        return str_replace(
            [
                '|"JCHOPTIMIZE_COMMENT_START',
                '|"JCHOPTIMIZE_COMMENT_IMPORT_START',
                'JCHOPTIMIZE_COMMENT_END"|',
                'JCHOPTIMIZE_DELIMITER',
                '|"JCHOPTIMIZE_LINE_END"|'
            ],
            [
                "\n" . '/***! ',
                "\n" . "\n" . '/***! @import url',
                ' !***/' . "\n" . "\n",
                ($test) ? 'JCHOPTIMIZE_DELIMITER' : '',
                "\n"
            ],
            trim($contents)
        );
    }

    public function getJsContents(array $urlArray): array
    {
        return $this->getContents($urlArray, 'js');
    }

    /**
     * Used when you want to append the contents of files to some that are already combined, into one file
     *
     * @param   array   $ids          Array of ids of files that were already combined
     * @param   array   $fileMatches  Array of file matches to be combined
     * @param   string  $type         Type of files css|js
     *
     * @return array The contents of the combined files
     */
    public function appendFiles(array $ids, array $fileMatches, string $type): array
    {
        $contents = '';

        foreach ($ids as $id) {
            $contents .= Output::getCombinedFile([
                'f'    => $id,
                'type' => $type
            ], false);
        }

        try {
            $results = $this->combineFiles($fileMatches, $type);
        } catch (Exception $e) {
            $this->logger->error('Error appending files: ' . $e->getMessage());

            $results = [
                'content'   => '',
                'font-face' => [],
                'gfonts'    => [],
                'images'    => []
            ];
        }

        $contents .= $this->prepareContents($results['content']);

        $contents .= "\n" . 'jchOptimizeDynamicScriptLoader.next()';

        return [
            'filemtime'   => time(),
            'etag'        => md5($contents),
            'contents'    => $contents,
            'font-face'   => $results['font-face'],
            'preconnects' => $results['preconnects'],
            'images'      => $results['images']
        ];
    }
}
