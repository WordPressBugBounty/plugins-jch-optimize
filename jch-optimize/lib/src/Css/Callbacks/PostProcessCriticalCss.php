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

namespace JchOptimize\Core\Css\Callbacks;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use JchOptimize\Core\Css\Callbacks\Dependencies\CriticalCssDependencies;
use JchOptimize\Core\Css\Components\CssUrl;
use JchOptimize\Core\Css\Components\FontFaceAtRule;
use JchOptimize\Core\Css\Components\KeyFrames;
use JchOptimize\Core\Css\CssComponents;
use JchOptimize\Core\Css\ModifyCssUrlsProcessor;
use JchOptimize\Core\Css\ModifyCssUrlsTrait;
use JchOptimize\Core\Registry;

use function trim;

class PostProcessCriticalCss extends AbstractCallback implements ModifyCssUrlsProcessor
{
    use ModifyCssUrlsTrait;

    public function __construct(
        Container $container,
        Registry $params,
        private CriticalCssDependencies $dependencies
    ) {
        parent::__construct($container, $params);
    }

    public function getDependencies(): CriticalCssDependencies
    {
        return $this->dependencies;
    }

    private function prepareSrcValuesForHttp2Preload(FontFaceAtRule $cssComponent): void
    {
        $cssComponent->modifyCssUrls($this);
    }

    public function processCssUrls(CssUrl $cssUrl): ?CssUrl
    {
        $correctUrlsObj = $this->getContainer()->get(CorrectUrls::class)
            ->setContext('font-face')
            ->setHandlingCriticalCss(true);

        $correctUrlsObj->addHttpPreloadsToCacheObject($cssUrl->getUri());

        $this->cacheObject->merge($correctUrlsObj->getCacheObject());

        return $cssUrl;
    }

    protected function internalProcessMatches(CssComponents $cssComponent): string
    {
        if ($cssComponent instanceof FontFaceAtRule && $this->fontIncludedInCriticalCss($cssComponent)) {
            $this->prepareSrcValuesForHttp2Preload($cssComponent);

            if ($this->params->get('pro_optimizeFonts_enable', '0')) {
                $this->cacheObject->addFontFace([
                    'content' => $cssComponent->render(),
                    'media' => ''
                ]);

                return '';
            }

            return $cssComponent->render();
        }

        if ($cssComponent instanceof KeyFrames && $this->keyframeIncludedInCriticalCss($cssComponent)) {
            return $cssComponent->render();
        }

        $this->addToSecondaryCss($cssComponent);

        return '';
    }

    private function keyframeIncludedInCriticalCss(KeyFrames $cssComponent): bool
    {
        return str_contains($this->dependencies->getCriticalCssAggregate(), trim($cssComponent->getName()));
    }
    private function fontIncludedInCriticalCss(FontFaceAtRule $cssComponent): bool
    {
        return str_contains($this->dependencies->getCriticalCssAggregate(), $cssComponent->getFontFamily());
    }

    protected function supportedCssComponents(): array
    {
        return [
            FontFaceAtRule::class,
            KeyFrames::class,
        ];
    }

    public function postProcessModifiedCssComponent(CssComponents $cssComponent): void
    {
    }
}
