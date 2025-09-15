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
use JchOptimize\Core\Html\Elements\Link;
use JchOptimize\Core\Html\Elements\Script;
use JchOptimize\Core\Html\Elements\Style;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Platform\ProfilerInterface;
use JchOptimize\Core\Preloads\Http2Preload;
use JchOptimize\Core\Registry;
use JchOptimize\Core\Uri\Uri;
use JchOptimize\Core\Uri\Utils;

use function array_key_last;
use function array_pop;
use function array_shift;
use function defined;
use function extension_loaded;
use function file_exists;
use function implode;
use function ini_get;
use function preg_replace;
use function str_replace;
use function ucfirst;

use const JCH_DEBUG;
use const JCH_PRO;
use const PHP_EOL;

defined('_JCH_EXEC') or die('Restricted access');

class HtmlManager implements ContainerAwareInterface, EventManagerAwareInterface
{
    use ContainerAwareTrait;
    use EventManagerAwareTrait;

    protected $events = null;

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
        $headHtml = preg_replace('#<title[^>]*+>#i', $child . "\n\t" . '\0', $this->processor->getHeadHtml(), 1);
        $this->processor->setHeadHtml($headHtml);
    }

    /**
     * @throws PregErrorException
     */
    public function addCriticalCssToHead(string $criticalCss, string $id): void
    {
        //Remove CSS from HTML
        $replacements = $this->filesManager->cssReplacements[0];
        $html = $this->processor->getFullHtml();
        $html = str_replace($replacements, '', $html);
        $this->processor->setFullHtml($html);

        $criticalStyle = HtmlElementBuilder::style()
            ->class('jchoptimize-critical-css')
            ->id($id)
            ->addChild(PHP_EOL . $criticalCss . PHP_EOL)
            ->render();

        $this->appendChildToHead($criticalStyle, true);
    }

    /**
     * @throws PregErrorException
     */
    public function appendChildToHead(string $sChild, bool $bCleanReplacement = false): void
    {
        if ($bCleanReplacement) {
            $sChild = Helper::cleanReplacement($sChild);
        }

        $sHeadHtml = $this->processor->getHeadHtml();
        $sHeadHtml = preg_replace(
            '#' . Parser::htmlClosingHeadTagToken() . '#i',
            $sChild . PHP_EOL . "\t" . '\0',
            $sHeadHtml,
            1
        );

        $this->processor->setHeadHtml($sHeadHtml);
    }

    public function addExcludedJsToSection(string $section): void
    {
        $aExcludedJs = $this->filesManager->aExcludedJs;
        if (!empty($aExcludedJs)) {
            $html = $this->processor->getFullHtml();
            $html = str_replace($aExcludedJs, '', $html);
            $this->processor->setFullHtml($html);

            //Add excluded javascript files to the bottom of the HTML section
            $sExcludedJs = implode(PHP_EOL, $aExcludedJs);
            $sExcludedJs = Helper::cleanReplacement($sExcludedJs);

            $this->appendChildToHTML($sExcludedJs, $section);
        }
    }

    public function appendChildToHTML(string $child, string $section): void
    {
        $sSearchArea = preg_replace(
        /** @see Parser::htmlClosingHeadTagToken() */
        /** @see Parser::htmlClosingBodyTagToken() */
            '#' . Parser::{'htmlClosing' . ucfirst($section) . 'TagToken'}() . '#si',
            "\t" . $child . PHP_EOL . '\0',
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

    public function addDeferredJs(string $section): void
    {
        $deferredJsStorage = $this->filesManager->deferredScriptStorage;
        //Remove deferred files from original location
        $html = $this->processor->getFullHtml();

        foreach ($deferredJsStorage as $deferredJs) {
            $html = str_replace((string)$deferredJs, '', $html);
        }

        $this->processor->setFullHtml($html);

        //If we're loading javascript dynamically add the deferred javascript files to array
        // of files to load dynamically instead
        if ($this->params->get('pro_reduce_unused_js_enable', '0')) {
            $dynamicJs = $this->getContainer()->get(DynamicJs::class);
            if ($dynamicJs instanceof DynamicJs) {
                $dynamicJs->prepareJsDynamicUrls($deferredJsStorage);
            }
        } else {
            //Otherwise if there are any deferredJsStorage we just add them to the bottom of the page
            foreach ($deferredJsStorage as $deferredJs) {
                $this->appendChildToHTML((string)$deferredJs, $section);
            }
        }
    }

    /**
     * @throws PregErrorException
     */
    public function setImgAttributes($aCachedImgAttributes): void
    {
        $sHtml = $this->processor->getBodyHtml();
        $this->processor->setBodyHtml(str_replace($this->processor->images[0], $aCachedImgAttributes, $sHtml));
    }

    public function replaceLinks(
        HtmlElementInterface $element,
        string $type,
        string $section = 'head',
        int $linksKey = 0
    ): void {
        JCH_DEBUG ? $this->profiler->start('ReplaceLinks - ' . $type) : null;

        $searchArea = $this->processor->getFullHtml();

        //All js files after the last excluded js will be placed at bottom of section
        if ($element instanceof Script && $this->noMoreExcludedJsFiles($linksKey)) {
            //If last combined file is being inserted at the bottom of the page then
            //add the async or defer attribute
            if ($section == 'body') {
                if ($this->params->get('loadAsynchronous', '0')) {
                    //Add async attribute to last combined js file if option is set
                    $this->deferScript($element);
                }
            }

            //Remove replacements for this index
            $replacements = $this->filesManager->jsReplacements[$linksKey];
            $searchArea = str_replace($replacements, '', $searchArea);

            //Insert script tag at the appropriate section in the HTML
            $searchArea = preg_replace(
            /** @see Parser::htmlClosingHeadTagToken() */
            /** @see Parser::htmlClosingBodyTagToken() */
                '#' . Parser::{'htmlClosing' . ucfirst($section) . 'TagToken'}() . '#si',
                "\t" . $element->render() . PHP_EOL . '\0',
                $searchArea,
                1
            );
        } else {
            $newLink = $element->render();

            //Get replacements for this index
            $replacements = $this->filesManager->{$type . 'Replacements'}[$linksKey];
            //If CSS, place combined file at location of first file in array
            if ($type == 'css') {
                $marker = array_shift($replacements);
                //Otherwise, place combined file at location of last file in array
            } elseif (!empty($this->filesManager->jsMarker[$linksKey])) {
                //If a files was excluded PEO at this index, use as marker
                $marker = $this->filesManager->jsMarker[$linksKey];
                $newLink .= PHP_EOL . "\t" . $marker;
            } else {
                $marker = array_pop($replacements);
            }

            $searchArea = str_replace($marker, $newLink, $searchArea);
            $searchArea = str_replace($replacements, '', $searchArea);
        }

        $this->processor->setFullHtml($searchArea);

        JCH_DEBUG ? $this->profiler->stop('ReplaceLinks - ' . $type, true) : null;
    }

    public function noMoreExcludedJsFiles($index): bool
    {
        return (empty($this->filesManager->jsMarker)
            || $index > array_key_last($this->filesManager->jsMarker));
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

    public function loadCssAsync(Link|Style $cssElement): void
    {
        if ($cssElement instanceof Style && trim($cssElement->getChildren()[0]) == '') {
            return;
        }

        if (!$this->params->get('pro_reduce_unused_css', '0')) {
            $this->appendChildToHTML($this->preloadStyleSheet($cssElement, 'low'), 'body');
        }
    }

    public function loadCssDynamically(Link $dynamicCss): void
    {
        $this->appendChildToHTML(
            $dynamicCss->type('jchoptimize-text/css')->render(),
            'body'
        );
    }

    public function preloadStyleSheet(Link|Style $element, string $fetchPriority = 'auto'): string
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

        return $element->attributes($attr)->render();
    }

    public function getPreloadLink(array $attr): string
    {
        $link = HtmlElementBuilder::link()->rel('preload')->attributes($attr);

        return $link->render();
    }

    public function addCriticalJsToSection(): void
    {
        if (JCH_PRO) {
            /** @see DynamicJs::appendCriticalJsToHtml() */
            $this->getContainer()->get(DynamicJs::class)->appendCriticalJsToHtml();
        }
    }

    public function removeAutoLcp(): void
    {
        $this->processor->processAutoLcp();
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

    public function removeCSSLinks(int|string $cssLinksKey): void
    {
        $replacements = $this->filesManager->cssReplacements[$cssLinksKey];
        $html = str_replace($replacements, '', $this->processor->getHtml());

        $this->processor->setHtml($html);
    }

    public function removeJsLinks(int|string $jsLinksKey): void
    {
        $replacements = $this->filesManager->jsReplacements[$jsLinksKey];
        $html = str_replace($replacements, '', $this->processor->getHtml());

        $this->processor->setHtml($html);
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
