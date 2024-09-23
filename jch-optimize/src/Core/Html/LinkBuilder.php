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

use GuzzleHttp\Psr7\Uri;
use JchOptimize\Core\Cdn;
use JchOptimize\Core\Exception;
use JchOptimize\Core\FeatureHelpers\DynamicJs;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Http2Preload;
use JchOptimize\Core\Output;
use JchOptimize\Core\Uri\Utils;
use JchOptimize\Platform\Paths;
use JchOptimize\Platform\Profiler;
use Joomla\DI\ContainerAwareInterface;
use Joomla\DI\ContainerAwareTrait;
use Joomla\Filesystem\File;
use Joomla\Registry\Registry;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerAwareTrait;
use Laminas\EventManager\SharedEventManagerInterface;
use Psr\Http\Message\UriInterface;

use function defined;
use function extension_loaded;
use function file_exists;
use function implode;
use function ini_get;
use function preg_replace;
use function str_replace;
use function strtoupper;
use function ucfirst;

defined('_JCH_EXEC') or die('Restricted access');

/**
 *
 *
 */
class LinkBuilder implements ContainerAwareInterface, EventManagerAwareInterface
{
    use ContainerAwareTrait;
    use EventManagerAwareTrait;

    /**
     * @var Processor $oProcessor
     */
    private Processor $oProcessor;
    /**
     */
    private Registry $params;
    /**
     * @var AsyncManager
     */
    private AsyncManager $asyncManager;
    /**
     * @var FilesManager
     */
    private FilesManager $filesManager;
    /**
     * @var StorageInterface $cache
     */
    private StorageInterface $cache;
    /**
     * @var Cdn
     */
    private Cdn $cdn;
    /**
     * @var Http2Preload
     */
    private Http2Preload $http2Preload;

    /**
     * Constructor
     *
     * @param Registry $params
     * @param Processor $processor
     * @param FilesManager $filesManager
     * @param Cdn $cdn
     * @param Http2Preload $http2Preload
     * @param StorageInterface $cache
     * @param SharedEventManagerInterface $sharedEventManager
     */
    public function __construct(
        Registry                    $params,
        Processor                   $processor,
        FilesManager                $filesManager,
        Cdn                         $cdn,
        Http2Preload                $http2Preload,
        StorageInterface            $cache,
        SharedEventManagerInterface $sharedEventManager
    ) {
        $this->params = $params;
        $this->oProcessor = $processor;
        $this->filesManager = $filesManager;
        $this->cdn = $cdn;
        $this->http2Preload = $http2Preload;
        $this->cache = $cache;

        if (JCH_PRO) {
            $this->asyncManager = new AsyncManager($params);
        }

        $this->setEventManager(new EventManager($sharedEventManager));
    }

    public function prependChildToHead(string $child): void
    {
        $headHtml = preg_replace('#<title[^>]*+>#i', $child . "\n\t" . '\0', $this->oProcessor->getHeadHtml(), 1);
        $this->oProcessor->setHeadHtml($headHtml);
    }

    public function addCriticalCssToHead(string $criticalCss, ?string $id): void
    {
        $criticalStyle = '<style id="jch-optimize-critical-css" data-id="' . $id . '">' . "\n" .
            $criticalCss . "\n" .
            '</style>';

        $this->appendChildToHead($criticalStyle, true);
    }

    public function appendChildToHead(string $sChild, bool $bCleanReplacement = false): void
    {
        if ($bCleanReplacement) {
            $sChild = Helper::cleanReplacement($sChild);
        }

        $sHeadHtml = $this->oProcessor->getHeadHtml();
        $sHeadHtml = preg_replace(
            '#' . Parser::htmlClosingHeadTagToken() . '#i',
            $sChild . "\n\t" . '</head>',
            $sHeadHtml,
            1
        );

        $this->oProcessor->setHeadHtml($sHeadHtml);
    }

    public function addExcludedJsToSection(string $section): void
    {
        $aExcludedJs = $this->filesManager->aExcludedJs;

        //Add excluded javascript files to the bottom of the HTML section
        $sExcludedJs = implode("\n", $aExcludedJs['ieo']) . implode("\n", $aExcludedJs['peo']);
        $sExcludedJs = Helper::cleanReplacement($sExcludedJs);

        if ($sExcludedJs != '') {
            $this->appendChildToHTML($sExcludedJs, $section);
        }
    }

    public function appendChildToHTML(string $child, string $section): void
    {
        $sSearchArea = preg_replace(
            /** @see Parser::htmlClosingHeadTagToken() */
            /** @see Parser::htmlClosingBodyTagToken() */
            '#' . Parser::{'htmlClosing' . strtoupper($section) . 'TagToken'}() . '#si',
            "\t" . $child . "\n" . '</' . $section . '>',
            $this->oProcessor->getFullHtml(),
            1
        );
        $this->oProcessor->setFullHtml($sSearchArea);
    }

    public function addDeferredJs(string $section): void
    {
        $defers = $this->filesManager->defers;
        //If we're loading javascript dynamically add the deferred javascript files to array of files to load dynamically instead
        if ($this->params->get('pro_reduce_unused_js_enable', '0')) {
            /** @see DynamicJs::prepareJsDynamicUrls() */
            $this->container->get(DynamicJs::class)->prepareJsDynamicUrls($defers);
        } //Otherwise if there are any defers we just add them to the bottom of the page
        elseif (!empty($defers[0])) {
            foreach ($defers as $deferGroup) {
                foreach ($deferGroup as $deferArray) {
                    $this->appendChildToHTML($deferArray['script'], $section);
                }
            }
        }
    }

    public function setImgAttributes($aCachedImgAttributes): void
    {
        $sHtml = $this->oProcessor->getBodyHtml();
        $this->oProcessor->setBodyHtml(str_replace($this->oProcessor->images[0], $aCachedImgAttributes, $sHtml));
    }

    /**
     * Insert url of aggregated file in html
     *
     * @param string $id
     * @param string $type
     * @param string $section Whether section being processed is head|body
     * @param int $jsLinksKey Index key of javascript combined file
     *
     * @throws Exception\RuntimeException
     */
    public function replaceLinks(string $id, string $type, string $section = 'head', int $jsLinksKey = 0): void
    {
        JCH_DEBUG ? Profiler::start('ReplaceLinks - ' . $type) : null;

        $searchArea = $this->oProcessor->getFullHtml();

        //All js files after the last excluded js will be placed at bottom of section
        if ($type == 'js' && $jsLinksKey >= $this->filesManager->jsExcludedIndex
            && !empty($this->filesManager->aJs[$this->filesManager->iIndex_js])) {
            $url = $this->buildUrl($id, 'js');
            //If last combined file is being inserted at the bottom of the page then
            //add the async or defer attribute
            if ($section == 'body') {
                $defer = false;
                $async = false;

                if ($this->params->get('loadAsynchronous', '0')) {
                    if ($this->filesManager->bLoadJsAsync) {
                        $async = true;
                    } else {
                        $defer = true;
                    }
                }

                //Add async attribute to last combined js file if option is set
                $newLink = $this->getNewJsLink((string)$url, $defer, $async);
            } else {
                $newLink = $this->getNewJsLink((string)$url);
            }

            //Insert script tag at the appropriate section in the HTML
            $searchArea = preg_replace(
                /** @see Parser::htmlClosingHeadTagToken() */
                /** @see Parser::htmlClosingBodyTagToken() */
                '#' . Parser::{'htmlClosing' . strtoupper($section) . 'TagToken'}() . '#si',
                "\t" . $newLink . "\n" . '</' . $section . '>',
                $searchArea,
                1
            );

            $deferred = $this->filesManager->isFileDeferred($newLink);
            $this->http2Preload->add($url, $type, $deferred);
        } else {
            $url = $this->buildUrl($id, $type);

            $this->http2Preload->add($url, $type);
            $newLink = $this->{'getNew' . ucfirst($type) . 'Link'}($url);
            //Replace placeholders in HTML with combined files
            $searchArea = preg_replace('#<JCH_' . strtoupper($type) . '([^>]++)>#', $newLink, $searchArea, 1);
        }

        $this->oProcessor->setFullHtml($searchArea);

        JCH_DEBUG ? Profiler::stop('ReplaceLinks - ' . $type, true) : null;
    }

    /**
     * Returns url of aggregated file
     *
     * @param string $id
     * @param string $type css or js
     *
     * @return UriInterface Url of aggregated file
     */
    public function buildUrl(string $id, string $type): UriInterface
    {
        $htaccess = $this->params->get('htaccess', 2);
        $uri = Utils::uriFor(Paths::relAssetPath());

        switch ($htaccess) {
            case '1':
            case '3':

                $uri = ($htaccess == 3) ? $uri->withPath($uri->getPath() . '3') : $uri;
                $uri = $uri->withPath($uri->getPath() . Paths::rewriteBaseFolder()
                    . ($this->isGz() ? 'gz' : 'nz') . '/' . $id . '.' . $type);

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
                $uri = Utils::uriFor(Paths::cachePath());
                $uri = $uri->withPath($uri->getPath() . '/' . $type . '/' . $id . '.' . $type);// . ($this->isGz() ? '.gz' : '');

                $this->createStaticFiles($id, $type);

                break;
        }

        return $this->cdn->loadCdnResource($uri);
    }

    /**
     * Check if gzip is set or enabled
     *
     * @return bool   True if gzip parameter set and server is enabled
     */
    public function isGZ(): bool
    {
        return ($this->params->get('gzip', 0) && extension_loaded('zlib') && !ini_get('zlib.output_compression')
            && (ini_get('output_handler') != 'ob_gzhandler'));
    }

    /**
     * Create static combined file if not yet exists
     *
     *
     * @param string $id Cache id of file
     * @param string $type Type of file css|js
     *
     * @return void
     */
    protected function createStaticFiles(string $id, string $type): void
    {
        JCH_DEBUG ? Profiler::start('CreateStaticFiles - ' . $type) : null;

        //Get cache filesystem path to create file
        $uri = Utils::uriFor(Paths::cachePath(false));
        $uri = $uri->withPath($uri->getPath() . '/' . $type . '/' . $id . '.' . $type);
        //File path of combined file
        $combinedFile = (string)$uri;

        if (!file_exists($combinedFile)) {
            $vars = [
                'f' => $id,
                'type' => $type
            ];

            $content = Output::getCombinedFile($vars, false);

            if ($content === false) {
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

        JCH_DEBUG ? Profiler::stop('CreateStaticFiles - ' . $type, true) : null;
    }

    /**
     * @param string $url Url of file
     * @param bool $isDefer If true the 'defer attribute will be added to the script element
     * @param bool $isASync If true the 'async' attribute will be added to the script element
     *
     * @return string
     */
    public function getNewJsLink(string $url, bool $isDefer = false, bool $isASync = false): string
    {
        $deferAttr = $isDefer ? $this->getFormattedHtmlAttribute('defer') : '';
        $asyncAttr = $isASync ? $this->getFormattedHtmlAttribute('async') : '';

        return '<script src="' . $url . '"' . $asyncAttr . $deferAttr . '></script>';
    }

    /**
     * Returns HTML attribute properly formatted for XHTML/XML or HTML5
     *
     * @param string $attr
     *
     * @return string
     */
    private function getFormattedHtmlAttribute(string $attr): string
    {
        $attributeMap = [
            'async' => 'async',
            'defer' => 'defer',
            'crossorigin' => 'anonymous'
        ];

        return Helper::isXhtml(
            $this->oProcessor->getHtml()
        ) ? ' ' . $attr . '="' . (@$attributeMap[$attr] ?: $attr) . '"' : ' ' . $attr;
    }

    /**
     * @param UriInterface[] $cssUrls
     *
     * @psalm-param list{0?: UriInterface,...} $cssUrls
     */
    public function loadCssAsync(array $cssUrls): void
    {
        if (!$this->params->get('pro_reduce_unused_css', '0')) {
            foreach ($cssUrls as $url) {
                $this->appendChildToHead($this->getPreloadStyleSheet($url, 'all'));
            }
        } else {
            $this->asyncManager->loadCssAsync($cssUrls);
        }
    }

    public function getPreloadStyleSheet(string $url, string $media): string
    {
        $attr = [
            'as' => 'style',
            'onload' => 'this.rel=\'stylesheet\'',
            'href' => $url,
            'media' => $media
        ];

        return $this->getPreloadLink($attr);
    }

    public function getPreloadLink(array $attr): string
    {
        $crossorigin = !empty($attr['crossorigin']) ? ' ' . $this->getFormattedHtmlAttribute('crossorigin') : '';
        $media = !empty($attr['media']) ? ' media="' . $attr['media'] . '"' : '';
        $type = !empty($attr['type']) ? ' type="' . $attr['type'] . '"' : '';
        $onload = !empty($attr['onload']) ? ' onload="' . $attr['onload'] . '"' : '';

        return "<link rel=\"preload\" href=\"{$attr['href']}\" as=\"{$attr['as']}\"{$type}{$media}{$crossorigin}{$onload} />";
    }

    public function appendAsyncScriptsToHead(): void
    {
        if (JCH_PRO) {
            $sScript = $this->cleanScript($this->asyncManager->printHeaderScript());
            $this->appendChildToHead($sScript);
        }
    }

    /**
     *
     * @param string $script
     *
     * @return string
     */
    protected function cleanScript(string $script): string
    {
        if (!Helper::isXhtml($this->oProcessor->getHtml())) {
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

    public function addJsLazyLoadAssetsToHtml(string $id, string $section): void
    {
        $url = $this->buildUrl($id, 'js');
        $script = $this->getNewJsLink((string)$url, false, true);

        $this->appendChildToHTML($script, $section);
    }

    /**
     * @param string $url Url of file
     *
     * @return string
     */
    public function getNewCssLink(string $url): string
    {
        //language=HTML
        return '<link rel="stylesheet" href="' . $url . '" />';
    }

    public function getPreconnectLink(UriInterface $domainUri): string
    {
        $crossorigin = $this->getFormattedHtmlAttribute('crossorigin');

        $domain = Uri::composeComponents($domainUri->getScheme(), $domainUri->getHost(), '', '', '');

        //language=HTML
        return '<link rel="preconnect" href="' . $domain . '" ' . $crossorigin . ' />';
    }

    public function getModulePreloadLink(string $url): string
    {
        //language=HTML
        return '<link rel="modulepreload" href="' . $url . '" />';
    }

    public function preProcessHtml(): void
    {
        JCH_DEBUG ? Profiler::start('PreProcessHtml') : null;

        $this->getEventManager()->trigger(__FUNCTION__, $this);

        JCH_DEBUG ? Profiler::start('PreProcessHtml', true) : null;
    }

    public function postProcessHtml(): void
    {
        JCH_DEBUG ? Profiler::start('PostProcessHtml') : null;

        $this->getEventManager()->trigger(__FUNCTION__, $this);

        JCH_DEBUG ? Profiler::stop('PostProcessHtml', true) : null;
    }
}
