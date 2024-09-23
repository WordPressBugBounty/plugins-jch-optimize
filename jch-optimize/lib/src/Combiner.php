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

namespace JchOptimize\Core;

use _JchOptimizeVendor\GuzzleHttp\Client;
use _JchOptimizeVendor\GuzzleHttp\Exception\GuzzleException;
use _JchOptimizeVendor\GuzzleHttp\Psr7\UriResolver;
use _JchOptimizeVendor\GuzzleHttp\Psr7\Utils;
use _JchOptimizeVendor\GuzzleHttp\RequestOptions;
use _JchOptimizeVendor\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\Laminas\Cache\Pattern\CallbackCache;
use _JchOptimizeVendor\Laminas\Cache\Storage\IterableInterface;
use _JchOptimizeVendor\Laminas\Cache\Storage\StorageInterface;
use _JchOptimizeVendor\Laminas\Cache\Storage\TaggableInterface;
use _JchOptimizeVendor\Psr\Http\Client\ClientInterface;
use _JchOptimizeVendor\Psr\Http\Message\UriInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareTrait;
use CodeAlfa\Minify\Css;
use CodeAlfa\Minify\Js;
use CodeAlfa\RegexTokenizer\Debug\Debug;
use JchOptimize\Core\Container\ContainerAwareTrait;
use JchOptimize\Core\Css\Processor as CssProcessor;
use JchOptimize\Core\Css\Sprite\Generator;
use JchOptimize\Core\Uri\UriComparator;
use JchOptimize\Core\Uri\UriConverter;
use JchOptimize\Platform\Profiler;

\defined('_JCH_EXEC') or exit('Restricted access');

/**
 * Class to combine CSS/JS files together.
 */
class Combiner implements ContainerAwareInterface, LoggerAwareInterface, \Serializable
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;
    use Debug;
    use \JchOptimize\Core\FileInfosUtilsTrait;
    use \JchOptimize\Core\SerializableTrait;
    use \JchOptimize\Core\StorageTaggingTrait;

    public bool $isBackend = \false;

    private Registry $params;

    private CallbackCache $callbackCache;

    /**
     * @var null|(Client&ClientInterface)
     */
    private $http;

    /**
     * @var IterableInterface&StorageInterface&TaggableInterface
     */
    private $taggableCache;

    /**
     * Constructor.
     *
     * @param IterableInterface&StorageInterface&TaggableInterface $taggableCache
     * @param null|(Client&ClientInterface)                        $http
     */
    public function __construct(Registry $params, CallbackCache $callbackCache, $taggableCache, FileUtils $fileUtils, $http)
    {
        $this->params = $params;
        $this->callbackCache = $callbackCache;
        $this->taggableCache = $taggableCache;
        $this->fileUtils = $fileUtils;
        $this->http = $http;
    }

    public function getCssContents(array $urlArray): array
    {
        return $this->getContents($urlArray, 'css');
    }

    /**
     * Get aggregated and possibly minified content from js and css files.
     *
     * @param array  $urlArray Indexed multidimensional array of urls of css or js files for aggregation
     * @param string $type     css or js
     *
     * @return array Aggregated (and possibly minified) contents of files
     *
     * @throws \Exception
     */
    public function getContents(array $urlArray, string $type): array
    {
        !JCH_DEBUG ?: Profiler::start('GetContents - '.$type, \true);
        $aResult = $this->combineFiles($urlArray, $type);
        $sContents = $this->prepareContents($aResult['content']);
        if ('css' == $type) {
            if ($this->params->get('csg_enable', 0)) {
                try {
                    /** @var Generator $oSpriteGenerator */
                    $oSpriteGenerator = $this->container->get(Generator::class);
                    $aSpriteCss = $oSpriteGenerator->getSprite($sContents);
                    if (!empty($aSpriteCss) && !empty($aSpriteCss['needles']) && !empty($aSpriteCss['replacements'])) {
                        $sContents = \str_replace($aSpriteCss['needles'], $aSpriteCss['replacements'], $sContents);
                    }
                } catch (\Exception $ex) {
                    $this->logger->error($ex->getMessage());
                }
            }
            $sContents = $aResult['import'].$sContents;
            if (\function_exists('mb_convert_encoding')) {
                $sContents = '@charset "utf-8";'.$sContents;
            }
        }
        // Save contents in array to store in cache
        $aContents = ['filemtime' => \time(), 'etag' => \md5($sContents), 'contents' => $sContents, 'images' => \array_unique($aResult['images']), 'font-face' => $aResult['font-face'], 'preconnects' => $aResult['preconnects'], 'gfonts' => $aResult['gfonts'], 'bgselectors' => $aResult['bgselectors'], 'lcpImages' => $aResult['lcpImages']];
        !JCH_DEBUG ?: Profiler::stop('GetContents - '.$type);

        return $aContents;
    }

    /**
     * Aggregate contents of CSS and JS files.
     *
     * @param array  $fileInfosArray Array of links of files to combine
     * @param string $type           css|js
     * @param mixed  $cacheItems
     *
     * @return array Aggregated contents
     *
     * @throws \Exception
     */
    public function combineFiles(array $fileInfosArray, string $type, $cacheItems = \true): array
    {
        $responses = ['content' => '', 'import' => '', 'font-face' => [], 'preconnects' => [], 'images' => [], 'gfonts' => [], 'bgselectors' => [], 'lcpImages' => []];
        // Iterate through each file/script to optimize and combine
        foreach ($fileInfosArray as $fileInfos) {
            // Truncate url to less than 40 characters
            $sUrl = $this->prepareFileUrl($fileInfos, $type);
            !JCH_DEBUG ?: Profiler::start('CombineFile - '.$sUrl);
            // Try to store tags first
            $function = [$this, 'cacheContent'];
            $args = [$fileInfos, $type, \true];
            $id = $this->callbackCache->generateKey($function, $args);
            $this->tagStorage($id);
            // If caching set and tagging was successful we attempt to cache
            if ($cacheItems && !empty($this->taggableCache->getTags($id))) {
                // Optimize and cache file/script returning the optimized content
                $results = $this->callbackCache->call($function, $args);
                // Append to combined contents
                $responses['content'] .= $this->addCommentedUrl($type, $fileInfos).$results['content']."\n".'JCHOPTIMIZE_DELIMITER';
            } else {
                // If we're not caching just get the optimized content
                $results = $this->cacheContent($fileInfos, $type, \false);
                $responses['content'] .= $this->addCommentedUrl($type, $fileInfos).$results['content'].'|"JCHOPTIMIZE_LINE_END"|';
            }
            if ('css' == $type) {
                $responses['import'] .= $results['import'];
                $responses['images'] = \array_merge($responses['images'], $results['images']);
                $responses['gfonts'] = \array_merge($responses['gfonts'], $results['gfonts']);
                $responses['font-face'] = \array_merge($responses['font-face'], $results['font-face']);
                $responses['preconnects'] = \array_merge($responses['preconnects'], $results['preconnects']);
                $responses['bgselectors'] = \array_merge($responses['bgselectors'], $results['bgselectors']);
                $responses['lcpImages'] = \array_merge($responses['lcpImages'], $results['lcpImages']);
            }
            !JCH_DEBUG ?: Profiler::stop('CombineFile - '.$sUrl, \true);
        }

        return $responses;
    }

    /**
     * Optimize and cache contents of individual file/script returning optimized content.
     */
    public function cacheContent(array $fileInfos, string $type, bool $bPrepare): array
    {
        // Initialize content string
        $content = '';
        $responses = [];
        // If it's a file fetch the contents of the file
        if (isset($fileInfos['url'])) {
            $content .= $this->getFileContents($fileInfos['url']);
            // Remove zero-width non-breaking space
            $content = \trim($content, '﻿');
        } else {
            // If it's a declaration just use it
            $content .= $fileInfos['content'];
        }
        if ('css' == $type) {
            /** @var CssProcessor $oCssProcessor */
            $oCssProcessor = $this->container->get(CssProcessor::class);
            $oCssProcessor->setCssInfos($fileInfos);
            $oCssProcessor->setCss($content);
            $oCssProcessor->formatCss();
            $oCssProcessor->processUrls();
            $oCssProcessor->processMediaQueries();
            $oCssProcessor->processAtRules();
            $content = $oCssProcessor->getCss();
            $responses['import'] = $oCssProcessor->getImports();
            $responses['images'] = $oCssProcessor->getImages();
            $responses['font-face'] = $oCssProcessor->getFontFace();
            $responses['gfonts'] = $oCssProcessor->getGFonts();
            $responses['preconnects'] = $oCssProcessor->getPreconnects();
            $responses['bgselectors'] = $oCssProcessor->getCssBgImagesSelectors();
            $responses['lcpImages'] = $oCssProcessor->getLcpImages();
        }
        if ('js' == $type && '' != \trim($content)) {
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

    /**
     * Remove placeholders from aggregated file for caching.
     *
     * @param string $contents Aggregated file contents
     */
    public function prepareContents(string $contents, bool $test = \false): string
    {
        return \str_replace(['|"JCHOPTIMIZE_COMMENT_START', '|"JCHOPTIMIZE_COMMENT_IMPORT_START', 'JCHOPTIMIZE_COMMENT_END"|', 'JCHOPTIMIZE_DELIMITER', '|"JCHOPTIMIZE_LINE_END"|'], ["\n".'/***! ', "\n\n".'/***! @import url', ' !***/'."\n\n", $test ? 'JCHOPTIMIZE_DELIMITER' : '', "\n"], \trim($contents));
    }

    public function getJsContents(array $urlArray): array
    {
        return $this->getContents($urlArray, 'js');
    }

    /**
     * Used when you want to append the contents of files to some that are already combined, into one file.
     *
     * @param array  $ids         Array of ids of files that were already combined
     * @param array  $fileMatches Array of file matches to be combined
     * @param string $type        Type of files css|js
     *
     * @return array The contents of the combined files
     */
    public function appendFiles(array $ids, array $fileMatches, string $type): array
    {
        $contents = '';
        foreach ($ids as $id) {
            $contents .= \JchOptimize\Core\Output::getCombinedFile(['f' => $id, 'type' => $type], \false);
        }

        try {
            $results = $this->combineFiles($fileMatches, $type);
        } catch (\Exception $e) {
            $this->logger->error('Error appending files: '.$e->getMessage());
            $results = ['content' => '', 'font-face' => [], 'gfonts' => [], 'images' => []];
        }
        $contents .= $this->prepareContents($results['content']);
        $contents .= "\n".'jchOptimizeDynamicScriptLoader.next()';

        return ['filemtime' => \time(), 'etag' => \md5($contents), 'contents' => $contents, 'font-face' => $results['font-face'], 'preconnects' => $results['preconnects'], 'images' => $results['images']];
    }

    protected function addCommentedUrl(string $type, array $fileInfos): string
    {
        $comment = '';
        if ($this->params->get('debug', '1')) {
            $fileInfos = $fileInfos['url'] ?? ('js' == $type ? 'script' : 'style').' declaration';
            $comment = '|"JCHOPTIMIZE_COMMENT_START '.$fileInfos.' JCHOPTIMIZE_COMMENT_END"|';
        }

        return $comment;
    }

    private function getFileContents(UriInterface $uri): string
    {
        $uri = UriResolver::resolve(\JchOptimize\Core\SystemUri::currentUri(), $uri);
        if (!UriComparator::isCrossOrigin($uri)) {
            $filePath = UriConverter::uriToFilePath($uri);
            if (\file_exists($filePath) && \JchOptimize\Core\Helper::isStaticFile($filePath)) {
                try {
                    $stream = Utils::streamFor(Utils::tryFopen($filePath, 'r'));
                    if (!$stream->isReadable()) {
                        throw new \Exception('Stream unreadable');
                    }
                    if ($stream->isSeekable()) {
                        $stream->rewind();
                    }

                    return $stream->getContents();
                } catch (\Exception $e) {
                    $this->logger->warning('Couldn\'t open file: '.$uri.'; error: '.$e->getMessage());
                }
            }
        }

        try {
            $options = [RequestOptions::HEADERS => ['Accept-Enconding' => 'identity;q=0']];
            $response = $this->http->get($uri, $options);
            if (200 === $response->getStatusCode()) {
                // Get body and set pointer to beginning of stream
                $body = $response->getBody();
                $body->rewind();

                return $body->getContents();
            }

            return '|"JCHOPTIMIZE_COMMENT_START Response returned status code: '.$response->getStatusCode().' JCHOPTIMIZE_COMMENT_END"|';
        } catch (GuzzleException $e) {
            return '|"JCHOPTIMIZE_COMMENT_START Exception fetching file with message: '.$e->getMessage().' JCHOPTIMIZE_COMMENT_END"|';
        }
    }

    /**
     * Add try catch to contents of javascript file.
     */
    private function addErrorHandler(string $content, array $fileInfos): string
    {
        if (empty($fileInfos['module']) || 'module' != $fileInfos['module']) {
            $content = 'try {'."\n".$content."\n".'} catch (e) {'."\n";
            $content .= 'console.error(\'Error in ';
            $content .= isset($fileInfos['url']) ? 'file:'.$fileInfos['url'] : 'script declaration';
            $content .= '; Error:\' + e.message);'."\n".'};';
        }

        return $content;
    }

    /**
     * Add semicolon to end of js files if non exists;.
     */
    private function addSemiColon(string $content): string
    {
        $content = \rtrim($content);
        if (';' != \substr($content, -1) && !\preg_match('#\\|"JCHOPTIMIZE_COMMENT_START File[^"]+not found JCHOPTIMIZE_COMMENT_END"\\|#', $content)) {
            $content = $content.';';
        }

        return $content;
    }

    /**
     * Minify contents of fil.
     *
     * @return string $sMinifiedContent Minified content or original content if failed
     */
    private function minifyContent(string $content, string $type, array $fileInfos): string
    {
        if ($this->params->get($type.'_minify', 0)) {
            $url = $this->prepareFileUrl($fileInfos, $type);

            try {
                $minifiedContent = \trim('css' == $type ? Css::optimize($content) : Js::optimize($content));
            } catch (\Exception $e) {
                $this->logger->error(\sprintf('Error occurred trying to minify: %s', $url));
                $minifiedContent = $content;
            }
            $this->_debug($url, '', 'minifyContent');

            return $minifiedContent;
        }

        return $content;
    }
}
