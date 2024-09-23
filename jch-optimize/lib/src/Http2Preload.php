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

use _JchOptimizeVendor\GuzzleHttp\Psr7\UriResolver;
use _JchOptimizeVendor\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\Laminas\EventManager\Event;
use _JchOptimizeVendor\Psr\Http\Message\UriInterface;
use JchOptimize\Core\Container\ContainerAwareTrait;
use JchOptimize\Core\FeatureHelpers\Http2Excludes;
use JchOptimize\Core\Html\HtmlManager;
use JchOptimize\Core\Html\Processor;
use JchOptimize\Core\Uri\UriComparator;
use JchOptimize\Core\Uri\UriConverter;
use JchOptimize\Core\Uri\UriNormalizer;
use JchOptimize\Platform\Cache;
use JchOptimize\Platform\Hooks;

// No direct access
\defined('_JCH_EXEC') or exit('Restricted access');
class Http2Preload implements ContainerAwareInterface
{
    use ContainerAwareTrait;
    private bool $enable = \false;

    /**
     * @var Registry
     */
    private \JchOptimize\Core\Registry $params;

    /**
     * @var array multidimensional array of files to be preloaded whether by using a <link> element in the HTML or
     *            sending a Link Request Header to the server
     */
    private array $preloads = ['html' => [], 'link' => []];

    /**
     * @var Cdn
     */
    private \JchOptimize\Core\Cdn $cdn;
    private int $imgCounter = 0;
    private int $scriptCounter = 0;
    private int $styleCounter = 0;
    private int $fontCounter = 0;
    private bool $includesAdded = \false;

    public function __construct(Registry $params, Cdn $cdn)
    {
        $this->params = $params;
        $this->cdn = $cdn;
        if ($params->get('http2_push_enable', '0')) {
            $this->enable = \true;
        }
    }

    /**
     * @param UriInterface $uri        Url of file
     * @param string[]     $attributes
     *
     * @return false|void
     */
    public function add(UriInterface $uri, string $type, string $fetchPriority = 'auto', array $attributes = [])
    {
        if (!$this->enable) {
            return;
        }
        if (!$this->validateUri($uri)) {
            return \false;
        }
        if (JCH_PRO) {
            // @see Http2Excludes::findHttp2Excludes()
            if ($this->container->get(Http2Excludes::class)->findHttp2Excludes($uri)) {
                return \false;
            }
        }
        $uri = UriResolver::resolve(\JchOptimize\Core\SystemUri::currentUri(), $uri);
        // Skip external files
        if (UriComparator::isCrossOrigin($uri)) {
            return \false;
        }
        $type = $this->normalizeType($type);
        if (!$this->checkType($uri, $type)) {
            return \false;
        }
        $this->internalAdd($uri, $type, $this->extension($uri), $fetchPriority, $attributes);
    }

    public function preload(UriInterface $uri, string $type, string $fontExt, string $fetchPriority = '', array $attributes = []): void
    {
        if ($this->validateUri($uri)) {
            $this->internalAdd($uri, $type, $fontExt, $fetchPriority, $attributes);
        }
    }

    public function addAdditional(UriInterface $uri, string $type, string $fontExt, string $fetchPriority = 'auto', array $attributes = []): void
    {
        if ($this->enable) {
            $this->preload($uri, $type, $fontExt, $fetchPriority, $attributes);
        }
    }

    public function isEnabled(): bool
    {
        return $this->enable;
    }

    public function addPreloadsToHtml(Event $event): void
    {
        $preloads = $this->getPreloads();

        /** @var HtmlManager $htmlManager */
        $htmlManager = $event->getTarget();
        foreach ($preloads['html'] as $preload) {
            $link = $htmlManager->getPreloadLink($preload);
            $htmlManager->prependChildToHead($link);
        }
    }

    public function getPreloads(): array
    {
        if (!$this->includesAdded) {
            $this->addIncludesToPreload();
            $this->includesAdded = \true;
            $this->preloads = Hooks::onHttp2GetPreloads($this->preloads);
        }

        return $this->preloads;
    }

    public function addIncludesToPreload(): void
    {
        if (JCH_PRO) {
            // @see Http2Excludes::addHttp2Includes()
            $this->container->get(Http2Excludes::class)->addHttp2Includes();
        }
    }

    public function addModulePreloadsToHtml(Event $event): void
    {
        if ($this->enable && JCH_PRO && $this->params->get('pro_http2_preload_modules', '1')) {
            /** @var Processor $htmlProcessor */
            $htmlProcessor = $this->container->get(Processor::class);
            $modules = $htmlProcessor->processModulesForPreload();

            /** @var HtmlManager $htmlManager */
            $htmlManager = $event->getTarget();
            foreach ($modules[4] as $module) {
                $link = $htmlManager->getModulePreloadLink($module);
                $htmlManager->prependChildToHead($link);
            }
        }
    }

    private function validateUri(UriInterface $uri): bool
    {
        return '' !== (string) $uri && 'data' !== $uri->getScheme();
    }

    private function extension(UriInterface $uri): string
    {
        return \pathinfo($uri->getPath(), \PATHINFO_EXTENSION);
    }

    private function checkType(UriInterface $uri, $type): bool
    {
        if ('image' == $type) {
            if ($this->imgCounter++ > 5) {
                return \false;
            }
        }
        if ('script' == $type) {
            if ($this->scriptCounter++ > 5) {
                return \false;
            }
        }
        if ('css' == $type) {
            if ($this->styleCounter++ > 5) {
                return \false;
            }
        }
        if (!\in_array($type, $this->params->get('pro_http2_file_types', ['style', 'script', 'font', 'image']))) {
            return \false;
        }
        if ('font' == $type) {
            // Only push fonts of type woff/woff2
            if (!\in_array($this->extension($uri), ['woff', 'woff2'])) {
                return \false;
            }
            if ($this->fontCounter++ > 5) {
                return \false;
            }
        }

        return \true;
    }

    /**
     * @param string[] $attributes
     */
    private function internalAdd(UriInterface $uri, string $type, string $fontExt = '', string $fetchPriority = 'auto', array $attributes = []): void
    {
        $RR_uri = $this->cdn->loadCdnResource(UriNormalizer::normalize($uri));
        // If resource not on CDN we can remove scheme and host
        if (!$this->cdn->isFileOnCdn($RR_uri) && !UriComparator::isCrossOrigin($RR_uri)) {
            $RR_uri = UriConverter::absToNetworkPathReference($RR_uri);
        }
        $preload = \array_merge(['href' => (string) $RR_uri, 'as' => $type], $attributes);
        if ('auto' != $fetchPriority) {
            $preload['fetchpriority'] = $fetchPriority;
        }
        if ('font' == $type) {
            $preload['crossorigin'] = '';
            $ttfVersion = $preload;
            $woffVersion = $preload;
            $woff2Version = $preload;
            $ttfVersion['href'] = \preg_replace('#(?<=\\.)'.\preg_quote($fontExt).'#', 'ttf', $preload['href']);
            $ttfVersion['type'] = 'font/ttf';
            $woffVersion['href'] = \preg_replace('#(?<=\\.)'.\preg_quote($fontExt).'#', 'woff', $preload['href']);
            $woffVersion['type'] = 'font/woff';
            $woff2Version['href'] = \preg_replace('#(?<=\\.)'.\preg_quote($fontExt).'#', 'woff2', $preload['href']);
            $woff2Version['type'] = 'font/woff2';

            switch ($fontExt) {
                case 'ttf':
                    foreach ($this->preloads as $preloads) {
                        // If we already have the woff or woff2 version, abort
                        if (\in_array($woffVersion, $preloads) || \in_array($woff2Version, $preloads)) {
                            return;
                        }
                    }
                    $preload = $ttfVersion;

                    break;

                case 'woff':
                    foreach ($this->preloads as $preloadKey => $preloads) {
                        // If we already have the woff2 version of this file, abort
                        if (\in_array($woff2Version, $preloads)) {
                            return;
                        }
                        // if we already have the ttf version of this file, let's remove
                        // it and preload the woff version instead
                        $key = \array_search($ttfVersion, $preloads);
                        if (\false !== $key) {
                            unset($this->preloads[$preloadKey][$key]);
                        }
                    }
                    $preload = $woffVersion;

                    break;

                case 'woff2':
                    foreach ($this->preloads as $preloadsKey => $preloads) {
                        // If we already have the woff version of this file,
                        // let's remove it and preload the woff2 version instead
                        $woff_key = \array_search($woffVersion, $preloads);
                        if (\false !== $woff_key) {
                            unset($this->preloads[$preloadsKey][$woff_key]);
                        }
                        // If we already have the ttf version of this file,
                        // let's remove it also
                        $ttf_key = \array_search($ttfVersion, $preloads);
                        if (\false !== $ttf_key) {
                            unset($this->preloads[$preloadsKey][$ttf_key]);
                        }
                    }
                    $preload = $woff2Version;

                    break;

                default:
                    break;
            }
        }
        // We need to decide how we're going to preload this file.
        // If it's loaded by CDN or if we're using Capture cache we need
        // to put it in the HTML, otherwise we can send a link header, better IMO.
        // Let's make the default method 'link'
        $method = 'link';
        if ($this->cdn->isFileOnCdn($RR_uri) || UriComparator::isCrossOrigin($RR_uri) || Cache::isPageCacheEnabled($this->params, \true) && JCH_PRO && $this->params->get('pro_capture_cache_enable', '1') && !$this->params->get('pro_cache_platform', '0') || 'auto' != $fetchPriority || !empty($attributes)) {
            $method = 'html';
        }
        if (!\in_array($preload, $this->preloads[$method])) {
            $this->preloads[$method][] = $preload;
        }
    }

    private function normalizeType(string $type): string
    {
        $typeMap = ['js' => 'script', 'css' => 'style', 'font' => 'font', 'image' => 'image'];

        return $typeMap[$type];
    }
}
