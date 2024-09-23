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

use GuzzleHttp\Psr7\UriResolver;
use JchOptimize\Core\FeatureHelpers\Http2Excludes;
use JchOptimize\Core\Html\LinkBuilder;
use JchOptimize\Core\Html\Processor;
use JchOptimize\Core\Uri\UriComparator;
use JchOptimize\Core\Uri\UriConverter;
use JchOptimize\Core\Uri\UriNormalizer;
use JchOptimize\Platform\Cache;
use JchOptimize\Platform\Hooks;
use Joomla\DI\ContainerAwareInterface;
use Joomla\DI\ContainerAwareTrait;
use Joomla\Registry\Registry;
use Laminas\EventManager\Event;
use Psr\Http\Message\UriInterface;
use function array_search;
use function defined;
use function implode;
use function in_array;
use function preg_match;
use function preg_quote;
use function preg_replace;

// No direct access
defined('_JCH_EXEC') or die('Restricted access');

class Http2Preload implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    private bool $enable = false;
    /**
     * @var Registry
     */
    private Registry $params;
    /**
     * @var array multidimensional array of files to be preloaded whether by using a <link> element in the HTML or
     * sending a Link Request Header to the server
     */
    private array $aPreloads = [
        'html' => [],
        'link' => []
    ];
    /**
     * @var Cdn
     */
    private Cdn $cdn;

    private bool $includesAdded = false;

    public function __construct(Registry $params, Cdn $cdn)
    {
        $this->params = $params;
        $this->cdn = $cdn;

        if ($params->get('http2_push_enable', '0')) {
            $this->enable = true;
        }
    }

    /**
     * @param UriInterface $uri Url of file
     * @param string $type
     * @param bool $isDeferred
     *
     * @return false|void
     */
    public function add(UriInterface $uri, string $type, bool $isDeferred = false)
    {
        if (!$this->enable) {
            return;
        }

        if ((string)$uri == '' || $uri->getScheme() == 'data') {
            return false;
        }

        if (JCH_PRO) {
            /** @see Http2Excludes::findHttp2Excludes() */
            if ($this->container->get(Http2Excludes::class)->findHttp2Excludes($uri, $isDeferred)) {
                return false;
            }
        }

        $uri = UriResolver::resolve(SystemUri::currentUri(), $uri);
        //Skip external files
        if (UriComparator::isCrossOrigin($uri)) {
            return false;
        }

        if ($this->params->get('cookielessdomain_enable', '0')) {
            static $sCdnFileTypesRegex = '';

            if (empty($sCdnFileTypesRegex)) {
                $sCdnFileTypesRegex = implode('|', $this->cdn->getCdnFileTypes());
            }

            //If this file type will be loaded by CDN don't push if option not set
            if ($sCdnFileTypesRegex != '' && preg_match('#\.(?>' . $sCdnFileTypesRegex . ')#i', $uri->getPath())
                && !$this->params->get('pro_http2_push_cdn', '0')
            ) {
                return false;
            }
        }

        if ($type == 'image') {
            static $no_image = 0;

            if ($no_image++ > 10) {
                return false;
            }
        }

        if ($type == 'js') {
            static $no_js = 0;

            if ($no_js++ > 10) {
                return false;
            }

            $type = 'script';
        }

        if ($type == 'css') {
            static $no_css = 0;

            if ($no_css++ > 10) {
                return false;
            }

            $type = 'style';
        }

        if (!in_array(
            $type,
            $this->params->get('pro_http2_file_types', [
                'style',
                'script',
                'font',
                'image'
            ])
        )) {
            return false;
        }

        if ($type == 'font') {
            //Only push fonts of type woff/woff2
            if (preg_match("#\.\K(?:woff2?|ttf)(?=$|[\#?])#", $uri->getPath(), $m) == '1') {
                static $no_font = 0;

                if ($no_font++ > 10) {
                    return false;
                }

                $this->internalAdd($uri, $type, $m[0]);
            } else {
                return false;
            }
        } else {
            //Populate preload variable
            $this->internalAdd($uri, $type);
        }
    }

    /**
     * @param UriInterface $uri
     * @param string $type
     * @param string $ext
     */
    private function internalAdd(UriInterface $uri, string $type, string $ext = ''): void
    {
        $RR_uri = $this->cdn->loadCdnResource(UriNormalizer::normalize($uri));

        //If resource not on CDN we can remove scheme and host
        if (!$this->cdn->isFileOnCdn($RR_uri) && !UriComparator::isCrossOrigin($RR_uri)) {
            $RR_uri = UriConverter::absToNetworkPathReference($RR_uri);
        }

        $preload = [
            'href' => (string)$RR_uri,
            'as' => $type,
            'crossorigin' => false
        ];

        if ($type == 'font') {
            $preload['crossorigin'] = true;

            $ttfVersion = $preload;
            $woffVersion = $preload;
            $woff2Version = $preload;

            $ttfVersion['href'] = preg_replace('#(?<=\.)' . preg_quote($ext) . '#', 'ttf', $preload['href']);
            $ttfVersion['type'] = 'font/ttf';

            $woffVersion['href'] = preg_replace('#(?<=\.)' . preg_quote($ext) . '#', 'woff', $preload['href']);
            $woffVersion['type'] = 'font/woff';

            $woff2Version['href'] = preg_replace('#(?<=\.)' . preg_quote($ext) . '#', 'woff2', $preload['href']);
            $woff2Version['type'] = 'font/woff2';

            switch ($ext) {
                case 'ttf':
                    foreach ($this->aPreloads as $preloads) {
                        //If we already have the woff or woff2 version, abort
                        if (in_array($woffVersion, $preloads) ||
                            in_array($woff2Version, $preloads)) {
                            return;
                        }
                    }

                    $preload = $ttfVersion;

                    break;
                case 'woff':
                    foreach ($this->aPreloads as $preloadKey => $preloads) {
                        //If we already have the woff2 version of this file, abort
                        if (in_array($woff2Version, $preloads)) {
                            return;
                        }
                        //if we already have the ttf version of this file, let's remove
                        //it and preload the woff version instead
                        $key = array_search($ttfVersion, $preloads);

                        if ($key !== false) {
                            unset($this->aPreloads[$preloadKey][$key]);
                        }
                    }

                    $preload = $woffVersion;

                    break;
                case 'woff2':
                    foreach ($this->aPreloads as $preloadsKey => $preloads) {
                        //If we already have the woff version of this file,
                        // let's remove it and preload the woff2 version instead
                        $woff_key = array_search($woffVersion, $preloads);

                        if ($woff_key !== false) {
                            unset($this->aPreloads[$preloadsKey][$woff_key]);
                        }

                        //If we already have the ttf version of this file,
                        //let's remove it also
                        $ttf_key = array_search($ttfVersion, $preloads);

                        if ($ttf_key !== false) {
                            unset($this->aPreloads[$preloadsKey][$ttf_key]);
                        }
                    }

                    $preload = $woff2Version;

                    break;
                default:
                    break;
            }
        }

        //We need to decide how we're going to preload this file. If it's loaded by CDN or if we're using Capture cache we need
        //to put it in the HTML, otherwise we can send a link header, better IMO.
        //Let's make the default method 'link'
        $method = 'link';

        if ($this->cdn->isFileOnCdn($RR_uri)
            || UriComparator::isCrossOrigin($RR_uri)
            || (Cache::isPageCacheEnabled($this->params, true)
                && JCH_PRO && $this->params->get('pro_capture_cache_enable', '1')
                && !$this->params->get('pro_cache_platform', '0'))

        ) {
            $method = 'html';
        }

        if (!in_array($preload, $this->aPreloads[$method])) {
            $this->aPreloads[$method][] = $preload;
        }
    }

    public function addAdditional(UriInterface $uri, string $type, string $ext): void
    {
        $this->internalAdd($uri, $type, $ext);
    }

    public function isEnabled(): bool
    {
        return $this->enable;
    }

    public function addPreloadsToHtml(Event $event): void
    {
        $preloads = $this->getPreloads();
        /** @var LinkBuilder $linkBuilder */
        $linkBuilder = $event->getTarget();

        foreach ($preloads['html'] as $preload) {
            $link = $linkBuilder->getPreloadLink($preload);
            $linkBuilder->prependChildToHead($link);
        }
    }

    public function getPreloads(): array
    {
        if (!$this->includesAdded) {
            $this->addIncludesToPreload();
            $this->includesAdded = true;

            $this->aPreloads = Hooks::onHttp2GetPreloads($this->aPreloads);
        }

        return $this->aPreloads;
    }

    public function addIncludesToPreload(): void
    {
        if (JCH_PRO) {
            /** @see Http2Excludes::addHttp2Includes() */
            $this->container->get(Http2Excludes::class)->addHttp2Includes();
        }
    }

    public function addModulePreloadsToHtml(Event $event): void
    {
        if ($this->enable && JCH_PRO && $this->params->get('pro_http2_preload_modules', '1')) {
            /** @var Processor $htmlProcessor */
            $htmlProcessor = $this->container->get(Processor::class);
            $modules = $htmlProcessor->processModulesForPreload();
            /** @var LinkBuilder $linkBuilder */
            $linkBuilder = $event->getTarget();

            foreach ($modules[4] as $module) {
                $link = $linkBuilder->getModulePreloadLink($module);
                $linkBuilder->prependChildToHead($link);
            }
        }
    }
}
