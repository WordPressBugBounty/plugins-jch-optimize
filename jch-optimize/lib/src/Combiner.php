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

use _JchOptimizeVendor\V91\GuzzleHttp\Client;
use _JchOptimizeVendor\V91\GuzzleHttp\Exception\GuzzleException;
use _JchOptimizeVendor\V91\GuzzleHttp\Psr7\UriResolver;
use _JchOptimizeVendor\V91\GuzzleHttp\Psr7\Utils;
use _JchOptimizeVendor\V91\GuzzleHttp\RequestOptions;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareTrait;
use _JchOptimizeVendor\V91\Laminas\Stdlib\StringUtils;
use _JchOptimizeVendor\V91\Psr\Http\Client\ClientInterface;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareTrait;
use CodeAlfa\Minify\Css;
use CodeAlfa\Minify\Html;
use CodeAlfa\Minify\Js;
use Exception;
use JchOptimize\Core\Cdn\Cdn;
use JchOptimize\Core\Css\Callbacks\Dependencies\CriticalCssDomainProfiler;
use JchOptimize\Core\Css\CssProcessor;
use JchOptimize\Core\Exception\FileNotFoundException;
use JchOptimize\Core\Exception\PropertyNotFoundException;
use JchOptimize\Core\Exception\RuntimeException;
use JchOptimize\Core\Html\CacheManager;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Platform\ProfilerInterface;
use JchOptimize\Core\Uri\UriComparator;
use JchOptimize\Core\Uri\UriConverter;
use Serializable;

use function defined;
use function file_exists;
use function function_exists;
use function json_encode;
use function preg_match;
use function sprintf;
use function str_ends_with;
use function trim;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const PHP_EOL;

defined('_JCH_EXEC') or die('Restricted access');

/**
 * Class to combine CSS/JS files together
 */
class Combiner implements ContainerAwareInterface, LoggerAwareInterface, Serializable
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;
    use SerializableTrait;

    private bool $isLastKey = false;

    public function __construct(
        private Registry $params,
        /**
         * @var (Client&ClientInterface)|null
         */
        private $http,
        private ProfilerInterface $profiler
    ) {
    }

    /**
     * @throws Exception
     */
    public function getCssContents(array $fileInfosArray): CacheObject
    {
        return $this->getContents($fileInfosArray, 'css');
    }

    /**
     * @throws Exception
     */
    public function getContents(array $fileInfosArray, string $type): CacheObject
    {
        !JCH_DEBUG ?: $this->profiler->start('GetContents - ' . $type, true);

        $resultObj = $this->combineFiles($fileInfosArray);

        if ($type == 'css') {
            if (!$this->params->get('optimizeCssDelivery_enable', '0')) {
                $resultObj->prependContents($resultObj->getImports());
            } else {
                $resultObj->setCriticalCss($resultObj->getImports() . $resultObj->getCriticalCss());
            }

            $this->addCharset($resultObj);
        }

        $resultObj->prepareForCaching();

        !JCH_DEBUG ?: $this->profiler->stop('GetContents - ' . $type);

        return $resultObj;
    }

    /**
     * @throws Exception
     */
    public function combineFiles(array $fileInfosArray, bool $cacheItems = true): CacheObject
    {
        $cacheObj = new CacheObject();

        /** @var FileInfo $fileInfos */
        foreach ($fileInfosArray as $fileInfos) {
            $url = $fileInfos->display();
            $truncatedUrl = FileUtils::prepareFileForDisplay(Uri\Utils::uriFor($url));

            !JCH_DEBUG ?: $this->profiler->start('CombineFile - ' . $truncatedUrl);

            if ($cacheItems && $this->params->get('combine_files', '0')) {
                $cacheManager = $this->getContainer()->get(CacheManager::class);
                $resultObj = $cacheManager->cacheContent($fileInfos, $this->isLastKey);
            } else {
                $resultObj = $this->cacheContent($fileInfos);
            }

            $cacheObj->merge($resultObj->getMergedImportedContents());
            $cacheObj->appendContents(PHP_EOL);

            !JCH_DEBUG ?: $this->profiler->stop('CombineFile - ' . $truncatedUrl, true);
        }

        return $cacheObj;
    }

    /**
     * Optimize and cache contents of individual file/script returning optimized content
     */
    public function cacheContent(FileInfo $fileInfos): CacheObject
    {
        $cacheObj = new CacheObject();

        try {
            if ($fileInfos->hasUri()) {
                $content = $this->getFileContents($fileInfos);
            } else {
                $content = $fileInfos->getContent();
            }

            if (!$fileInfos->isAlreadyProcessed()) {
                $content = $this->removeHtmlComments($content, $fileInfos);

                if ($fileInfos->getType() == 'css') {
                    $cacheObj->merge($this->processCssInfos($content, $fileInfos));
                    $content = $cacheObj->getContents();
                }

                if ($fileInfos->getType() == 'js') {
                    $content = $this->addSemiColon($content);
                    $content = $this->addTryCatch($content, $fileInfos);
                }
            }

            $content = $this->minifyContent($content, $fileInfos);
            $content = $this->addCommentedUrl($fileInfos) . $content;
        } catch (FileNotFoundException $e) {
            $content = $e->getMessage();
        }

        $cacheObj->setContents($content);

        return $cacheObj;
    }

    public function getFileContents(FileInfo $fileInfo): string
    {
        $uri = UriResolver::resolve(SystemUri::currentUri(), $fileInfo->getUri());
        $cdn = $this->getContainer()->get(Cdn::class);
        $paths = $this->getContainer()->get(PathsInterface::class);

        if (UriComparator::existsLocally($uri, $cdn, $paths)) {
            $pathsUtils = $this->getContainer()->get(PathsInterface::class);
            $filePath = UriConverter::uriToFilePath($uri, $pathsUtils, $cdn);

            if (file_exists($filePath) && Helper::isStaticFile($filePath)) {
                try {
                    return $this->readStreamFromDisk($filePath);
                } catch (RuntimeException $e) {
                    $this->logger?->debug('Couldn\'t open file: ' . $uri . '; error: ' . $e->getMessage());
                }
            }
        }

        try {
            return $this->getResponseFromHttpRequest($uri, $fileInfo);
        } catch (GuzzleException | FileNotFoundException $e) {
            throw new FileNotFoundException(
                $this->wrapInComments($fileInfo->display()) . $e->getMessage()
            );
        }
    }

    /**
     * @throws RuntimeException
     */
    private function readStreamFromDisk(string $filePath): string
    {
        $stream = Utils::streamFor(Utils::tryFopen($filePath, 'r'));

        if (!$stream->isReadable()) {
            throw new RuntimeException('Stream unreadable');
        }

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $stream->getContents();
    }

    /**
     * @throws GuzzleException|FileNotFoundException
     */
    private function getResponseFromHttpRequest(UriInterface $uri, FileInfo $fileInfo): string
    {
        $options = [
            RequestOptions::HEADERS => [
                'Accept-Encoding' => 'identity;q=0'
            ]
        ];

        try {
            $response = $this->getHttp()->get($uri, $options);
        } catch (GuzzleException $e) {
            throw new FileNotFoundException("/* {$e->getMessage()} */");
        }

        $contentType = $response->getHeader('Content-Type')[0] ?? '';
        $expectedContentType = $this->getExpectedContentTypeRegex($fileInfo);

        if (!preg_match("#$expectedContentType#i", $contentType)) {
            throw new FileNotFoundException("/* Wrong Content-Type returned: $contentType */");
        }

        if ($response->getStatusCode() === 200) {
            $body = $response->getBody();
            $body->rewind();

            return $body->getContents();
        } else {
            throw new FileNotFoundException("/* Response returned status code: {$response->getStatusCode()} */");
        }
    }

    /**
     * @return Client&ClientInterface
     * @throw PropertyNotFoundException
     */
    public function getHttp()
    {
        if ($this->http === null) {
            throw new PropertyNotFoundException('Http Client not set');
        }

        return $this->http;
    }

    private function getExpectedContentTypeRegex(FileInfo $fileInfo): string
    {
        if ($fileInfo->getType() == 'js') {
            return '(?:text|application)/(?:x-)??(?:java|ecma|live|j)script';
        } else {
            return 'text/css';
        }
    }

    private function wrapInComments(string $message): string
    {
        $commentStart = '/***!';
        $commentEnd = '!***/';
        $message = str_replace([$commentStart, $commentEnd], '', $message);

        return PHP_EOL . "$commentStart  $message  $commentEnd" . PHP_EOL . PHP_EOL;
    }

    private function removeHtmlComments(string $content, FileInfo $fileInfos): string
    {
        return Html::cleanScript($content, $fileInfos->getType());
    }

    private function processCssInfos(string $content, FileInfo $fileInfos): CacheObject
    {
        /** @var CssProcessor $oCssProcessor */
        $oCssProcessor = $this->getContainer()->getNewInstance(CssProcessor::class);
        $oCssProcessor->setCssInfos($fileInfos);
        $oCssProcessor->setCss($content);
        $oCssProcessor->setIsLastKey($this->isLastKey);
        $oCssProcessor->formatCss();
        $oCssProcessor->processAtRules();
        $oCssProcessor->processConditionalAtRules();
        $oCssProcessor->optimizeCssDelivery();
        $oCssProcessor->processUrls();
        $oCssProcessor->processSprite();

        return $oCssProcessor->getCacheObj();
    }

    public function setIsLastKey(bool $isLastKey): Combiner
    {
        $this->isLastKey = $isLastKey;

        return $this;
    }

    private function addSemiColon(string $content): string
    {
        $content = trim($content);

        if ($content !== '' && !str_ends_with($content, ';')) {
            $content .= ';';
        }

        return $content;
    }

    private function addTryCatch(string $content, FileInfo $fileInfo): string
    {
        if (
            $this->params->get('combine_files', '0')
            && $this->params->get('try_catch', '1')
        ) {
            $content = <<<JS
try{
$content
} catch (e) {
console.error('Error in {$fileInfo->display()}; Error: ' + e.message);
}
JS;
        }

        return $content;
    }

    private function minifyContent(string $content, FileInfo $fileInfo): string
    {
        if ($this->params->get($fileInfo->getType() . '_minify', 0)) {
            $url = $fileInfo->display();

            try {
                $minifiedContent = trim(
                    $fileInfo->getType() == 'css' ? Css::optimize($content) : Js::optimize($content)
                );
            } catch (Exception $e) {
                $this->logger?->error(sprintf('Error occurred trying to minify: %s', $url));
                $minifiedContent = $content;
            }

            return $minifiedContent;
        }

        return $content;
    }

    public function addCommentedUrl(FileInfo $fileInfos): string
    {
        $comment = '';

        if ($this->params->get('debug', '1')) {
            $comment = $this->wrapInComments($fileInfos->display());
        }

        return $comment;
    }

    private function addCharset(CacheObject $resultObj): void
    {
        if (
            (function_exists('mb_detect_encoding')
                && mb_detect_encoding($resultObj->getContents(), 'UTF-8', true) === 'UTF-8')
            || (!function_exists('mb_detect_encoding')
                && StringUtils::hasPcreUnicodeSupport()
                && StringUtils::isValidUtf8($resultObj->getContents()))
        ) {
            $resultObj->prependContents('@charset "UTF-8";');
        }
    }

    /**
     * @throws Exception
     */
    public function getJsContents(array $fileInfosArray): CacheObject
    {
        return $this->getContents($fileInfosArray, 'js');
    }

    /**
     * Used when you want to append the contents of files to some that are already combined, into one file
     */
    public function appendFiles(array $ids, array $fileInfosArray, string $type): CacheObject
    {
        $resultObj = new CacheObject();

        if (!empty($ids)) {
            foreach ($ids as $id) {
                $resultObj->merge($this->handleIdForCombining($id, $type));
            }
        }

        if (!empty($fileInfosArray)) {
            try {
                $resultObj->merge($this->combineFiles($fileInfosArray));
            } catch (Exception $e) {
                $this->logger?->error('Error appending files: ' . $e->getMessage());
            }
        }

        if ($type == 'css') {
            $resultObj->prependContents($resultObj->getImports());
            $this->addCharset($resultObj);
        }
     /*   if ($type == 'js') {
            $resultObj->appendContents("\n" . 'jchOptimizeDynamicScriptLoader.next();');
        } */
        $resultObj->prepareForCaching();

        return $resultObj;
    }

    private function handleIdForCombining(mixed $id, string $type): CacheObject
    {
        $content = Output::getCombinedFile([
            'f' => $id,
            'type' => $type
        ], false);

        if ($type == 'css') {
            /** @var CssProcessor $cssProcessor */
            $cssProcessor = $this->getContainer()->getNewInstance(CSSProcessor::class);

            return $cssProcessor->processDynamicCssFile($content);
        } else {
            return (new CacheObject())->setContents($content);
        }
    }
}
