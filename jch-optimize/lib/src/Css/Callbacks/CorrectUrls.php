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

namespace JchOptimize\Core\Css\Callbacks;

use _JchOptimizeVendor\V91\GuzzleHttp\Psr7\UriResolver;
use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use JchOptimize\Core\Cdn\Cdn;
use JchOptimize\Core\Css\Components\CssRule;
use JchOptimize\Core\Css\Components\CssUrl;
use JchOptimize\Core\Css\CssComponents;
use JchOptimize\Core\Css\ModifyCssUrlsProcessor;
use JchOptimize\Core\Css\ModifyCssUrlsTrait;
use JchOptimize\Core\FeatureHelpers\LazyLoadExtended;
use JchOptimize\Core\FeatureHelpers\LCPImages;
use JchOptimize\Core\FeatureHelpers\ResponsiveImages;
use JchOptimize\Core\FeatureHelpers\Webp;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Platform\UtilityInterface;
use JchOptimize\Core\Preloads\Http2Preload;
use JchOptimize\Core\Registry;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\Uri;
use JchOptimize\Core\Uri\UriComparator;

use function defined;
use function in_array;
use function str_contains;

defined('_JCH_EXEC') or die('Restricted access');

class CorrectUrls extends AbstractCallback implements ModifyCssUrlsProcessor
{
    use ModifyCssUrlsTrait;

    private string $context = 'css-rule';

    private bool $handlingCriticalCss = false;

    public function __construct(
        Container $container,
        Registry $params,
        private Cdn $cdn,
        private Http2Preload $http2Preload,
        private UtilityInterface $utility
    ) {
        parent::__construct($container, $params);
    }

    public function setHandlingCriticalCss(bool $handlingCriticalCss): CorrectUrls
    {
        $this->handlingCriticalCss = $handlingCriticalCss;

        return $this;
    }

    public function setContext(string $context): CorrectUrls
    {
        $this->context = $context;

        return $this;
    }

    protected function internalProcessMatches(CssComponents $cssComponent): string
    {
        if (
            $cssComponent instanceof CssRule
            && str_contains($cssComponent->getDeclarationList(), 'url(')
        ) {
            $this->processCssRule($cssComponent);
        }

        return $cssComponent->render();
    }

    public function processCssRule(CssRule $cssComponent): void
    {
        $cssComponent->modifyCssUrls($this);
        $this->context = 'css-rule';

        $this->postProcessCssRule($cssComponent);
    }

    public function processCssUrls(CssUrl $cssUrl): CssUrl
    {
        $cssUrl->setUri($this->processUri($cssUrl->getUri()));

        return $cssUrl;
    }

    public function processUri(UriInterface $originalUri): UriInterface
    {
        if (
            $originalUri->getScheme() !== 'data'
            && $originalUri->getPath() != ''
            && $originalUri->getPath() != '/'
        ) {
            $imageUri = $this->resolveImageToFileUrl($originalUri);
            $paths = $this->getContainer()->get(PathsInterface::class);

            if (UriComparator::existsLocally($imageUri, $this->cdn, $paths)) {
                $this->cacheImageForAdminUsers($imageUri);
                $imageUri = $this->cdn->loadCdnResource($imageUri);
            } elseif ($this->params->get('pro_preconnect_domains_enable', '0')) {
                $this->prefetchExternalDomains($imageUri);
            }

            if ($this->context == 'css-rule') {
                $imageUri = $this->applyFeatureHelpers($imageUri);
            }

            $this->addHttpPreloadsToCacheObject($imageUri);

            return $imageUri;
        }

        return $originalUri;
    }

    private function resolveImageToFileUrl(UriInterface $originalUri): UriInterface
    {
        //Get the url of the file that contained the CSS
        $cssFileUri = $this->getCssInfo()->hasUri() ? $this->getCssInfo()->getUri() : new Uri();
        $cssFileUri = UriResolver::resolve(SystemUri::currentUri(), $cssFileUri);
        return UriResolver::resolve($cssFileUri, $originalUri);
    }

    private function prefetchExternalDomains(UriInterface $imageUri): void
    {
        $domain = $imageUri->withPath('')->withQuery('')->withFragment('');

        if (!in_array($domain, $this->cacheObject->getPrefetches())) {
            $this->cacheObject->addPrefetches($domain);
        }
    }

    private function cacheImageForAdminUsers(UriInterface $imageUri): void
    {
        //Collect local images if running in admin. Used by Optimize Images and MultiSelect exclude
        if (
            $this->utility->isAdmin()
            && !in_array((string)$imageUri, $this->cacheObject->getImages())
            && $this->context != 'font-face'
        ) {
            $this->cacheObject->addImages($imageUri);
        }
    }

    private function applyFeatureHelpers(UriInterface $imageUri): UriInterface
    {
        $responsiveImages = [];
        //We need to get responsive images before URI is converted to WEBP
        if (JCH_PRO && $this->params->get('pro_load_responsive_images', '0')) {
            $responsiveImages = $this->getContainer()->get(ResponsiveImages::class)
                /** @see ResponsiveImages::getResponsiveImages() */
               ->getResponsiveImages($imageUri);
        }

        if (JCH_PRO && $this->params->get('pro_load_webp_images', '0')) {
            /** @see Webp::getWebpImages() */
            $webpImageUri = $this->getContainer()->get(Webp::class)->getWebpImages($imageUri);

            //If Webp were generated, add them to ResponsiveImages, so we can identify them later
            if ($webpImageUri !== $imageUri && !empty($responsiveImages)) {
                $this->getContainer()->get(ResponsiveImages::class)
                   ->responsiveImages[(string)$webpImageUri] = $responsiveImages;
            }

            $imageUri = $webpImageUri;
        }

        if (JCH_PRO && $this->handlingCriticalCss && $this->params->get('pro_lcp_images_enable', '0')) {
            $this->getContainer()->get(LCPImages::class)
                /** @see LCPImages::prepareBackgroundLcpImages() */
                ->prepareBackgroundLcpImages($imageUri, $this->cacheObject);
        }

        return $imageUri;
    }

    private function lazyLoadCssRule(CssRule $cssRule, &$lazyLoaded = false): void
    {
        if (
            JCH_PRO && $this->params->get('lazyload_enable', '0')
            && $this->params->get('pro_lazyload_bgimages', '0')
            && !$this->handlingCriticalCss
            && !str_contains($cssRule->render(), '.jch-lazyload')
        ) {
            $this->getContainer()->get(LazyLoadExtended::class)
                /** @see LazyLoadExtended::handleCssBgImages() */
                ->handleCssBgImages(
                    $this,
                    $cssRule,
                    $lazyLoaded
                );
        }
    }

    protected function supportedCssComponents(): array
    {
        return [
            CssRule::class
        ];
    }

    public function addHttpPreloadsToCacheObject(UriInterface $imageUri): void
    {
        if (
            $this->params->get('http2_push_enable', '0')
            && $this->handlingCriticalCss
        ) {
            $fileType = match ($this->context) {
                'font-face' => 'font',
                'import' => 'css',
                default => 'image'
            };

            $cacheItems = [
                [
                    'src' => $imageUri,
                    'as' => $fileType
                ]
            ];
            if (JCH_PRO && $this->params->get('pro_load_responsive_images', '0') && $fileType == 'image') {
                $cacheItems = $this->getContainer()->get(ResponsiveImages::class)
                    /** @see ResponsiveImages::mergeResponsiveImageCacheItems() */
                        ->mergeResponsiveImageCacheItems($cacheItems);
            }
            foreach ($cacheItems as $cacheItem) {
                $this->cacheObject->addHttp2Preloads($cacheItem);
            }
        }
    }

    public function postProcessCssRule(CssRule $cssComponent): void
    {
        if (JCH_PRO && str_contains($cssComponent->getDeclarationList(), 'url(')) {
            $this->lazyLoadCssRule($cssComponent);
            $this->makeCssRuleResponsive($cssComponent);
        }
    }

    public function postProcessModifiedCssComponent(CssComponents $cssComponent): void
    {
    }

    private function makeCssRuleResponsive(CssRule $cssRule): void
    {
        if (JCH_PRO && $this->params->get('pro_load_responsive_images', '0')) {
            $this->getContainer()->get(ResponsiveImages::class)
                /** @see ResponsiveImages::makeCssRuleResponsive() */
                ->makeCssRuleResponsive($cssRule);
        }
    }
}
