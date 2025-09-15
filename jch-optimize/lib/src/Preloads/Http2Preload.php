<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Preloads;

use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareTrait;
use _JchOptimizeVendor\V91\Laminas\EventManager\Event;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use JchOptimize\Core\Cdn\Cdn;
use JchOptimize\Core\Exception;
use JchOptimize\Core\FeatureHelpers\Http2Excludes;
use JchOptimize\Core\Html\Elements\Script;
use JchOptimize\Core\Html\HtmlElementBuilder;
use JchOptimize\Core\Html\HtmlManager;
use JchOptimize\Core\Html\HtmlProcessor;
use JchOptimize\Core\Platform\CacheInterface;
use JchOptimize\Core\Platform\HooksInterface;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Platform\UtilityInterface;
use JchOptimize\Core\Registry;
use JchOptimize\Core\Uri\UriComparator;
use JchOptimize\Core\Uri\UriConverter;
use JchOptimize\Core\Uri\UriNormalizer;
use JchOptimize\Core\Uri\Utils;
use SplObjectStorage;

use function array_merge;
use function defined;
use function in_array;

// No direct access
defined('_JCH_EXEC') or die('Restricted access');

class Http2Preload implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    private ?bool $enable = null;

    private PreloadsCollection $imagePreloads;

    private PreloadsCollection $fontPreloads;

    private PreloadsCollection $stylePreloads;

    private PreloadsCollection $scriptPreloads;

    private PreloadsCollection $otherPreloads;

    private ?SplObjectStorage $preloads = null;

    private int $imgCounter = 0;

    private int $scriptCounter = 0;

    private int $styleCounter = 0;

    private int $fontCounter = 0;

    private bool $includesAdded = false;

    public function __construct(
        private Registry $params,
        private Cdn $cdn,
        private CacheInterface $cacheUtils,
        private HooksInterface $hooks,
        private UtilityInterface $utility
    ) {
        $this->imagePreloads = new PreloadsCollection();
        $this->fontPreloads = new PreloadsCollection();
        $this->stylePreloads = new PreloadsCollection();
        $this->scriptPreloads = new PreloadsCollection();
        $this->otherPreloads = new PreloadsCollection();
    }

    public function add(
        UriInterface $uri,
        string $fileType,
        array $attributes = []
    ): void {
        if (!$this->enabled()) {
            return;
        }

        if (!$this->validateUri($uri)) {
            return;
        }

        $fileType = $this->normalizeType($fileType);

        if (!$this->validateType($uri, $fileType)) {
            return;
        }

        $this->internalAdd($uri, $fileType, $attributes);
    }

    public function enabled(): bool
    {
        if ($this->enable === null) {
            $this->enable = $this->params->get('http2_push_enable', '0');
        }

        return $this->enable;
    }

    private function validateUri(UriInterface $uri): bool
    {
        if (!$this->isUriValid($uri)) {
            return false;
        }

        if (JCH_PRO) {
            /** @see Http2Excludes::findHttp2Excludes() */
            if ($this->getContainer()->get(Http2Excludes::class)->findHttp2Excludes($uri)) {
                return false;
            }
        }

        return true;
    }

    public function isUriValid(UriInterface $uri): bool
    {
        return (string)$uri !== '' && $uri->getScheme() !== 'data';
    }

    private function normalizeType(string $type): string
    {
        $typeMap = [
            'js' => 'script',
            'css' => 'style',
            'font' => 'font',
            'image' => 'image'
        ];

        return $typeMap[$type];
    }

    private function validateType(UriInterface $uri, $type): bool
    {
        if (
            !in_array(
                $type,
                $this->params->get('pro_http2_file_types', [
                    'style',
                    'script',
                    'font',
                    'image'
                ])
            )
        ) {
            return false;
        }

        if ($type == 'font') {
            //Only push fonts of type woff/woff2
            if (!in_array(Utils::fileExtension($uri), ['woff', 'woff2'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string[] $attributes
     */
    private function internalAdd(
        UriInterface $uri,
        string $fileType,
        array $attributes = []
    ): void {
        $RR_uri = $this->prepareUriForPreload($uri);

        $preload = new Preload($RR_uri, $fileType, $attributes);
        $this->attachPreload($preload);
    }

    public function prepareUriForPreload(UriInterface $uri): UriInterface
    {
        $RR_uri = $this->cdn->loadCdnResource(UriNormalizer::normalize($uri));

        //If resource not on CDN we can remove scheme and host
        $paths = $this->getContainer()->get(PathsInterface::class);
        if (!$this->cdn->isFileOnCdn($RR_uri) && UriComparator::existsLocally($RR_uri, $this->cdn, $paths)) {
            $RR_uri = UriConverter::absToNetworkPathReference($RR_uri);
        }

        return $RR_uri;
    }

    public function preload(
        UriInterface $uri,
        string $type,
        array $attributes = []
    ): void {
        if ($this->isUriValid($uri)) {
            $this->internalAdd($uri, $type, $attributes);
        }
    }

    public function addModulePreloadsToHtml(Event $event): void
    {
        if (
            JCH_PRO
            && (
                (
                    $this->enabled()
                    && $this->params->get('pro_http2_preload_modules', '1')
                )
                || (
                    $this->params->get('pro_reduce_unused_js_enable', '0')
                    && !$this->params->get('pro_defer_criticalJs', '1')
                )
            )
        ) {
            /** @var HtmlProcessor $htmlProcessor */
            $htmlProcessor = $this->getContainer()->get(HtmlProcessor::class);
            $modules = $htmlProcessor->processModulesForPreload();
            /** @var HtmlManager $htmlManager */
            $htmlManager = $event->getTarget();

            foreach ($modules as $module) {
                try {
                    $element = HtmlElementBuilder::load($module[0]);
                } catch (Exception\PregErrorException) {
                    continue;
                }

                if ($element instanceof Script) {
                    $link = $htmlManager->getModulePreloadLink($element->getSrc());
                    try {
                        $htmlManager->prependChildToHead($link);
                    } catch (Exception\PregErrorException $e) {
                    }
                }
            }
        }
    }

    public function preloadAssets(Event $event): void
    {
        $this->addIncludesToPreload();
        $this->sendLinkHeaders();
        $this->addPreloadsToHtml($event);
    }

    public function addIncludesToPreload(): void
    {
        if (JCH_PRO) {
            /** @see Http2Excludes::addHttp2Includes() */
            $this->getContainer()->get(Http2Excludes::class)->addHttp2Includes();
        }
    }

    public function sendLinkHeaders(): void
    {
        $headers = $this->generateLinkHeaders();

        if (!empty($headers)) {
            $this->utility->sendHeaders($headers);
        }
    }

    public function generateLinkHeaders(): array
    {
        $preloadHeaders = [];

        foreach ($this->getPreloads() as $preload) {
            if ($preload instanceof Preload && $preload->supportsLinkHeaders()) {
                $preloadHeaders[] = $preload->printLinkHeader();
            }
        }

        $headers = [];

        if (!empty($preloadHeaders)) {
            $headers['Link'] = implode(', ', $preloadHeaders);
        }

        return $headers;
    }

    public function addPreloadsToHtml(Event $event): void
    {
        /** @var HtmlManager $htmlManager */
        $htmlManager = $event->getTarget();

        foreach ($this->getPreloads() as $preload) {
            if ($preload instanceof Preload) {
                try {
                    $htmlManager->prependChildToHead($preload->renderPreloadLink());
                } catch (Exception\PregErrorException $e) {
                }
            }
        }
    }

    private function attachPreload(Preload $preload): void
    {
        switch ($preload->getAs()) {
            case 'font':
                if ($this->fontPreloads->count() < 2) {
                    $this->fontPreloads->attach($preload);
                }
                break;
            case 'image':
                if ($this->imagePreloads->count() < 4 || $preload->getFetchPriority() == 'high') {
                    $this->imagePreloads->attach($preload);
                }
                break;
            case 'style':
                $this->stylePreloads->attach($preload);
                break;
            case 'script':
                $this->scriptPreloads->attach($preload);
                break;
            default:
                $this->otherPreloads->attach($preload);
                break;
        }
    }

    private function getPreloads(): SplObjectStorage
    {
        if ($this->preloads instanceof SplObjectStorage) {
            return $this->preloads;
        }

        $preloads = new SplObjectStorage();
        $imagePreloads = $this->imagePreloads;
        $fontPreloads = $this->fontPreloads;
        $stylePreloads = $this->stylePreloads;
        $scriptPreloads = $this->scriptPreloads;
        $otherPreloads = $this->otherPreloads;

        if (JCH_PRO) {
            /** @var Http2Excludes $httpIncludes */
            $httpIncludes = $this->getContainer()->get(Http2Excludes::class);
            if ($httpIncludes->getImagePreloads()->count() > 0) {
                $imagePreloads = $httpIncludes->getImagePreloads();
            }
            if ($httpIncludes->getFontPreloads()->count() > 0) {
                $fontPreloads = $httpIncludes->getFontPreloads();
            }
            if ($httpIncludes->getStylePreloads()->count() > 0) {
                $stylePreloads = $httpIncludes->getStylePreloads();
            }
            if ($httpIncludes->getScriptPreloads()->count() > 0) {
                $scriptPreloads = $httpIncludes->getScriptPreloads();
            }
        }

        $preloads->addAll($imagePreloads);
        $preloads->addAll($fontPreloads);
        $preloads->addAll($stylePreloads);
        $preloads->addAll($scriptPreloads);
        $preloads->addAll($otherPreloads);

        $this->preloads = $this->hooks->onHttp2GetPreloads($preloads);

        return $this->preloads;
    }
}
