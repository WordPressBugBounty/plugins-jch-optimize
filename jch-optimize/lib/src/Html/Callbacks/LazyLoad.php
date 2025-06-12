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

namespace JchOptimize\Core\Html\Callbacks;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use Exception;
use JchOptimize\Core\Css\Components\CssUrl;
use JchOptimize\Core\Css\Parser as CssParser;
use JchOptimize\Core\Exception\InvalidArgumentException;
use JchOptimize\Core\FeatureHelpers\LazyLoadExtended;
use JchOptimize\Core\FeatureHelpers\LCPImages;
use JchOptimize\Core\FeatureHelpers\ResponsiveImages;
use JchOptimize\Core\FeatureHelpers\Webp;
use JchOptimize\Core\FeatureHelpers\YouTubeFacade;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Html\Elements\Audio;
use JchOptimize\Core\Html\Elements\Iframe;
use JchOptimize\Core\Html\Elements\Img;
use JchOptimize\Core\Html\Elements\Picture;
use JchOptimize\Core\Html\Elements\Video;
use JchOptimize\Core\Html\HtmlElementInterface;
use JchOptimize\Core\Preloads\Http2Preload;
use JchOptimize\Core\Registry;

use function array_merge;
use function defined;
use function implode;
use function preg_match;

use const JCH_PRO;

defined('_JCH_EXEC') or die('Restricted access');

class LazyLoad extends AbstractCallback
{
    protected array $excludes = [];

    protected array $includes = [];

    protected array $args = [];
    /**
     * @var int Width of <img> element inside <picture>
     */
    public int $width = 1;
    /**
     * @var int Height of <img> element inside <picture>
     */
    public int $height = 1;

    public function __construct(Container $container, Registry $params, public Http2Preload $http2Preload)
    {
        parent::__construct($container, $params);

        $this->getLazyLoadExcludes();
    }

    protected function getLazyLoadExcludes(): void
    {
        $aExcludesFiles = Helper::getArray($this->params->get('excludeLazyLoad', []));
        $aExcludesFolders = Helper::getArray($this->params->get('pro_excludeLazyLoadFolders', []));
        $aExcludesUrl = array_merge(['data:image'], $aExcludesFiles, $aExcludesFolders);

        $aExcludeClass = Helper::getArray($this->params->get('pro_excludeLazyLoadClass', []));

        $this->excludes = ['url' => $aExcludesUrl, 'class' => $aExcludeClass];

        $includesFiles = Helper::getArray($this->params->get('includeLazyLoad', []));
        $includesFolders = Helper::getArray($this->params->get('includeLazyLoadFolders', []));
        $includesUrl = array_merge($includesFiles, $includesFolders);

        $includesClass = Helper::getArray($this->params->get('includeLazyLoadClass', []));

        $this->includes = ['url' => $includesUrl, 'class' => $includesClass];
    }

    protected function internalProcessMatches(HtmlElementInterface $element): string
    {
        if (JCH_PRO && $this->params->get('pro_load_responsive_images', '0')) {
            $this->loadResponsiveImages($element);
        }

        if (JCH_PRO && $this->params->get('pro_load_webp_images', '0')) {
            $this->loadWebpImages($element);
        }

        if (JCH_PRO && $this->params->get('pro_lcp_images_enable', '0')) {
            if ($this->lcpImageProcessed($element)) {
                return $element->render();
            }
        }

        $options = array_merge($this->args, ['parent' => '']);

        //LCP Images in style attributes are also processed here
        if ($this->elementExcluded($element)) {
            return $element->render();
        }

        if ($options['lazyload'] || $this->params->get('http2_push_enable', '0')) {
            $element = $this->lazyLoadElement($element, $options);
        }

        return $element->render();
    }

    private function lazyLoadElement(
        HtmlElementInterface $element,
        array $options
    ): HtmlElementInterface {
        if (
            $options['lazyload']
            && ($options['section'] == 'below_fold') || $this->elementIncluded($element)
        ) {
            //If no srcset attribute was found, modify the src attribute and add a data-src attribute
            if ($element instanceof Img || $element instanceof Iframe) {
                $element->loading('lazy');
            }

            if (JCH_PRO && ($element instanceof Audio || $element instanceof Video)) {
                /** @see LazyLoadExtended::lazyLoadAudioVideo() */
                $this->getContainer()->get(LazyLoadExtended::class)->lazyLoadAudioVideo($element);
            }

            if ($element instanceof Picture && $element->hasChildren()) {
                $this->lazyLoadChildren($element);
            }

            if ($options['parent'] !== '') {
                return $element;
            }

            if (JCH_PRO && $this->params->get('pro_lazyload_bgimages', '0')) {
                /** @see LazyLoadExtended::lazyLoadBgImages() */
                $this->getContainer()->get(LazyLoadExtended::class)->lazyLoadBgImages($element);
            }
        } elseif ($options['section'] == 'above_fold') {
            if (JCH_PRO && $element instanceof iFrame && $this->params->get('use_youtube_facade')) {
                $facade = $this->getContainer()->get(YouTubeFacade::class)
                    /** @see YouTubeFacade::convert() */
                    ->convert($element);
                if ($facade !== $element) {
                    $element = $facade;
                }
            }

            if ($element->hasAttribute('style')) {
                preg_match(
                    '#' . CssParser::cssUrlToken() . '#i',
                    $element->getStyle(),
                    $match
                );

                if (!empty($match[0])) {
                    try {
                        $cssUrl = CssUrl::load($match[0]);
                        $this->http2Preload->add($cssUrl->getUri(), 'image');
                    } catch (Exception) {
                    }
                }
            }

            if ($element instanceof Picture && $element->hasChildren()) {
                $this->lazyLoadChildren($element);
            }

            //If lazy-load enabled, remove loading="lazy" attributes from above the fold
            if ($options['lazyload'] && $element instanceof Img) {
                //Remove any lazy loading
                if ($element->hasAttribute('loading')) {
                    $element->loading('eager');
                }
            }
        }

        return $element;
    }

    protected function lazyLoadChildren($element): void
    {
        $options = $this->args;

        if (empty($options['parent'])) {
            $options['parent'] = $element;
        }

        //Process and add content of element if not self-closing
        foreach ($element->getChildren() as $index => $child) {
            if ($child instanceof Img) {
                $element->replaceChild($index, $this->lazyLoadElement($child, $options));
            }
        }
    }

    public function setLazyLoadArgs(array $args): void
    {
        $this->args = $args;
    }

    private function filter(HtmlElementInterface $element, string $filterMethod): bool
    {
        if ($filterMethod == 'exclude') {
            $filter = $this->excludes;
        } else {
            $filter = $this->includes;
        }

        //Exclude based on class
        if ($element->hasAttribute('class') || $element->hasAttribute('id')) {
            if ($this->filterByIdOrClass($element, $filter)) {
                //Remove any lazy loading from excluded images
                if ($filterMethod == 'exclude' && $element->hasAttribute('loading')) {
                    $element->attribute('loading', 'eager');
                }

                return true;
            }
        }

        //If a src attribute is found
        if ($element->hasAttribute('src')) {
            //Abort if this file is excluded
            if (Helper::findExcludes($filter['url'], (string)$element->attributeValue('src'))) {
                //Remove any lazy loading from excluded images
                if ($filterMethod == 'exclude' && $element->hasAttribute('loading')) {
                    $element->attribute('loading', 'eager');
                }

                return true;
            }
        }

        //If poster attribute was found we can also exclude using poster value
        if (JCH_PRO && $element instanceof Video && $element->hasAttribute('poster')) {
            if (Helper::findExcludes($filter['url'], $element->getPoster())) {
                return true;
            }
        }

        if (JCH_PRO && $element->hasAttribute('style')) {
            if (
                preg_match(
                    '#' . CssParser::cssUrlToken() . '#i',
                    $element->getStyle(),
                    $match
                )
            ) {
                try {
                    $cssUrl = CssUrl::load($match[0]);
                } catch (InvalidArgumentException) {
                    return false;
                }

                $imageUri = $cssUrl->getUri();
                //We check first for LCP images
                if (JCH_PRO && $this->params->get('pro_lcp_images_enable', '0')) {
                    $lcpImageObj = $this->getContainer()->get(LCPImages::class);

                    if ($lcpImageObj->preloadLcpImagePerViewPort($imageUri)) {
                        return true;
                    }
                }

                if (Helper::findExcludes($filter['url'], (string)$imageUri)) {
                    return true;
                }
            }
        }

        if ($element->hasChildren()) {
            foreach ($element->getChildren() as $child) {
                if ($child instanceof HtmlElementInterface && $this->filter($child, $filterMethod)) {
                    return true;
                }
            }
        }

        return false;
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
        return $this->getContainer()->get(LCPImages::class)
            /** @see LCPImages::process() */
            ->process($element);
    }

    private function filterByIdOrClass(HtmlElementInterface $element, array $filter): bool
    {
        if (is_array($class = $element->getClass())) {
            $classString = implode(' ', $class);

            if (Helper::findExcludes($filter['class'], $classString)) {
                return true;
            }
        }

        if (is_string($id = $element->getId())) {
            if (Helper::findExcludes($filter['class'], $id)) {
                return true;
            }
        }

        return false;
    }
}
