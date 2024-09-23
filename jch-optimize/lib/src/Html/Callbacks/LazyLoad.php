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

namespace JchOptimize\Core\Html\Callbacks;

use _JchOptimizeVendor\Joomla\DI\Container;
use JchOptimize\Core\Css\Parser as CssParser;
use JchOptimize\Core\Css\Processor;
use JchOptimize\Core\Exception\PregErrorException;
use JchOptimize\Core\FeatureHelpers\LazyLoadExtended;
use JchOptimize\Core\FeatureHelpers\LCPImages;
use JchOptimize\Core\FeatureHelpers\ResponsiveImages;
use JchOptimize\Core\FeatureHelpers\Webp;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Html\Elements\Audio;
use JchOptimize\Core\Html\Elements\Iframe;
use JchOptimize\Core\Html\Elements\Img;
use JchOptimize\Core\Html\Elements\Picture;
use JchOptimize\Core\Html\Elements\Style;
use JchOptimize\Core\Html\Elements\Video;
use JchOptimize\Core\Html\HtmlElementBuilder;
use JchOptimize\Core\Html\HtmlElementInterface;
use JchOptimize\Core\Http2Preload;
use JchOptimize\Core\Registry;
use JchOptimize\Core\Uri\Utils;

\defined('_JCH_EXEC') or exit('Restricted access');
class LazyLoad extends \JchOptimize\Core\Html\Callbacks\AbstractCallback
{
    public Http2Preload $http2Preload;

    /**
     * @var int Width of <img> element inside <picture>
     */
    public int $width = 1;

    /**
     * @var int Height of <img> element inside <picture>
     */
    public int $height = 1;
    protected array $excludes = [];
    protected array $includes = [];
    protected array $args = [];

    public function __construct(Container $container, Registry $params, Http2Preload $http2Preload)
    {
        parent::__construct($container, $params);
        $this->http2Preload = $http2Preload;
        $this->getLazyLoadExcludes();
    }

    public function processMatches(array $matches): string
    {
        if (empty($matches[0]) || empty($matches[1])) {
            return $matches[0];
        }

        try {
            $element = HtmlElementBuilder::load($matches[0]);
        } catch (PregErrorException $e) {
            return $matches[0];
        }
        // If we're lazy-loading background images in a style that wasn't combined
        if ($element instanceof Style && \JCH_PRO && ($this->params->get('pro_lazyload_bgimages', '0') || $this->params->get('pro_load_webp_images', '0'))) {
            /**
             * @var int    $index
             * @var string $child
             */
            foreach ($element->getChildren() as $index => $child) {
                $cssProcessor = $this->getContainer()->get(Processor::class);
                $cssProcessor->setCss($child);
                $cssProcessor->processUrls();
                $element->replaceChild($index, $cssProcessor->getCss());
            }

            return $element->render();
        }
        if (\JCH_PRO && $this->params->get('pro_load_responsive_images', '0')) {
            $this->loadResponsiveImages($element);
        }
        if (\JCH_PRO && $this->params->get('pro_load_webp_images', '0')) {
            $this->loadWebpImages($element);
        }
        if (\JCH_PRO && $this->params->get('pro_lcp_images_enable', '0')) {
            if ($this->lcpImageProcessed($element)) {
                return $element->render();
            }
        }
        $options = \array_merge($this->args, ['parent' => '']);
        // LCP Images in style attributes are also processed here
        if ($this->elementExcluded($element)) {
            return $element->render();
        }
        if ($options['lazyload'] || $this->params->get('pro_http2_push_enable', '0')) {
            $element = $this->lazyLoadElement($element, $options);
        }

        return $element->render();
    }

    public function setLazyLoadArgs(array $args): void
    {
        $this->args = $args;
    }

    protected function getLazyLoadExcludes(): void
    {
        $aExcludesFiles = Helper::getArray($this->params->get('excludeLazyLoad', []));
        $aExcludesFolders = Helper::getArray($this->params->get('pro_excludeLazyLoadFolders', []));
        $aExcludesUrl = \array_merge(['data:image'], $aExcludesFiles, $aExcludesFolders);
        $aExcludeClass = Helper::getArray($this->params->get('pro_excludeLazyLoadClass', []));
        $this->excludes = ['url' => $aExcludesUrl, 'class' => $aExcludeClass];
        $includesFiles = Helper::getArray($this->params->get('includeLazyLoad', []));
        $includesFolders = Helper::getArray($this->params->get('includeLazyLoadFolders', []));
        $includesUrl = \array_merge($includesFiles, $includesFolders);
        $includesClass = Helper::getArray($this->params->get('includesLazyLoadClass', []));
        $this->includes = ['url' => $includesUrl, 'class' => $includesClass];
    }

    protected function lazyLoadChildren($element): void
    {
        $options = $this->args;
        if (empty($options['parent'])) {
            $options['parent'] = $element;
        }
        // Process and add content of element if not self-closing
        foreach ($element->getChildren() as $index => $child) {
            if ($child instanceof Img) {
                $element->replaceChild($index, $this->lazyLoadElement($child, $options));
            }
        }
    }

    private function lazyLoadElement(HtmlElementInterface $element, array $options): HtmlElementInterface
    {
        if ($options['lazyload'] && 'below_fold' == $options['section'] || $this->elementIncluded($element)) {
            // If no srcset attribute was found, modify the src attribute and add a data-src attribute
            if ($element instanceof Img || $element instanceof Iframe) {
                $element->loading('lazy');
            }
            if (\JCH_PRO && ($element instanceof Audio || $element instanceof Video)) {
                // @see LazyLoadExtended::lazyLoadAudioVideo()
                $this->getContainer()->get(LazyLoadExtended::class)->lazyLoadAudioVideo($element);
            }
            if ($element instanceof Picture && $element->hasChildren()) {
                $this->lazyLoadChildren($element);
            }
            if ('' !== $options['parent']) {
                return $element;
            }
            if (\JCH_PRO && $this->params->get('pro_lazyload_bgimages', '0')) {
                // @see LazyLoadExtended::lazyLoadBgImages()
                $this->getContainer()->get(LazyLoadExtended::class)->lazyLoadBgImages($element);
            }
        } elseif ('above_fold' == $options['section']) {
            if ($element->hasAttribute('style')) {
                \preg_match('#'.CssParser::cssUrlWithCaptureValueToken(\true).'#i', $element->getStyle(), $match);
                if (!empty($match[1])) {
                    $this->http2Preload->add(Utils::uriFor($match[1]), 'image');
                }
            }
            // If lazy-load enabled, remove loading="lazy" attributes from above the fold
            if ($options['lazyload'] && $element instanceof Img) {
                // Remove any lazy loading
                if ($element->hasAttribute('loading')) {
                    $element->loading('eager');
                }
            }
        }

        return $element;
    }

    private function filter(HtmlElementInterface $element, string $filterMethod): bool
    {
        if ('exclude' == $filterMethod) {
            $filter = $this->excludes;
        } else {
            $filter = $this->includes;
        }
        // Exclude based on class
        if ($element->hasAttribute('class')) {
            if (Helper::findExcludes($filter['class'], \implode(' ', $element->getClass()))) {
                // Remove any lazy loading from excluded images
                if ($element->hasAttribute('loading')) {
                    $element->attribute('loading', 'eager');
                }

                return \true;
            }
        }
        // If a src attribute is found
        if ($element->hasAttribute('src')) {
            // Abort if this file is excluded
            if (Helper::findExcludes($filter['url'], (string) $element->attributeValue('src'))) {
                // Remove any lazy loading from excluded images
                if ($element->hasAttribute('loading')) {
                    $element->attribute('loading', 'eager');
                }

                return \true;
            }
        }
        // If poster attribute was found we can also exclude using poster value
        if (\JCH_PRO && $element instanceof Video && $element->hasAttribute('poster')) {
            if (Helper::findExcludes($filter['url'], $element->getPoster())) {
                return \true;
            }
        }
        if (\JCH_PRO && $element->hasAttribute('style')) {
            \preg_match('#'.CssParser::cssUrlWithCaptureValueToken(\true).'#i', $element->getStyle(), $match);
            if (!empty($match[1])) {
                $image = $match[1];
                // We check first for LCP images
                if ($this->params->get('pro_lcp_images_enable', '0')) {
                    $lcpImages = Helper::getArray($this->params->get('pro_lcp_images', []));
                    if (Helper::findMatches($lcpImages, $image)) {
                        $this->http2Preload->preload(Utils::uriFor($match[1]), 'image', '', 'high');

                        return \true;
                    }
                }
                if (Helper::findExcludes($filter['url'], $image)) {
                    return \true;
                }
            }
        }
        if ($element->hasChildren()) {
            foreach ($element->getChildren() as $child) {
                if ($child instanceof HtmlElementInterface && $this->elementExcluded($child)) {
                    return \true;
                }
            }
        }

        return \false;
    }

    private function elementExcluded(HtmlElementInterface $element): bool
    {
        return $this->filter($element, 'exclude');
    }

    private function elementIncluded(HtmlElementInterface $element): bool
    {
        return $this->filter($element, 'include');
    }

    private function loadWebpImages(HtmlElementInterface $element): void
    {
        if ($element->hasChildren()) {
            foreach ($element->getChildren() as $child) {
                if ($child instanceof HtmlElementInterface) {
                    $this->loadWebpImages($child);
                }
            }
        }
        $this->getContainer()->get(Webp::class)->convert($element);
    }

    private function loadResponsiveImages(HtmlElementInterface $element): void
    {
        if ($element->hasChildren()) {
            foreach ($element->getChildren() as $child) {
                if ($child instanceof HtmlElementInterface) {
                    $this->loadResponsiveImages($child);
                }
            }
        }
        $this->getContainer()->get(ResponsiveImages::class)->convert($element);
    }

    private function lcpImageProcessed(HtmlElementInterface $element): bool
    {
        $lcpImages = Helper::getArray($this->params->get('pro_lcp_images'));
        if (empty($lcpImages)) {
            return \false;
        }
        if ($element->hasChildren()) {
            foreach ($element->getChildren() as $child) {
                if ($child instanceof Img) {
                    if ($this->lcpImageProcessed($child)) {
                        return \true;
                    }
                }
            }
        }
        if ($element instanceof Img || $element instanceof Video) {
            return $this->getContainer()->get(LCPImages::class)->process($element);
        }

        return \false;
    }
}
