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

use _JchOptimizeVendor\V91\Laminas\Cache\Exception\ExceptionInterface;
use Exception;
use JchOptimize\Core\Combiner;
use JchOptimize\Core\Css\Components\CharsetAtRule;
use JchOptimize\Core\Css\Components\FontFaceAtRule;
use JchOptimize\Core\Css\Components\ImportAtRule;
use JchOptimize\Core\Css\Components\KeyFrames;
use JchOptimize\Core\Css\CssComponents;
use JchOptimize\Core\FileInfo;
use JchOptimize\Core\Html\FilesManager;

use function defined;
use function extension_loaded;
use function str_contains;

defined('_JCH_EXEC') or die('Restricted access');

class HandleAtRules extends AbstractCallback
{
    protected function internalProcessMatches(CssComponents $cssComponent): string
    {
        if ($cssComponent instanceof CharsetAtRule) {
            return '';
        }

        if ($cssComponent instanceof FontFaceAtRule) {
            $this->resolveFontFaceUri($cssComponent);
            $this->implementFontDisplayPolicy($cssComponent);

            return $this->preprocessOptimizeCssDelivery($cssComponent);
        }

        if ($cssComponent instanceof KeyFrames) {
            return $this->preprocessOptimizeCssDelivery($cssComponent);
        }

        if ($cssComponent instanceof ImportAtRule) {
            $this->resolveImportUri($cssComponent);

            if (str_contains($cssComponent->getUri()->getHost(), 'fonts.googleapis.com')) {
                $this->handleGoogleFonts($cssComponent);

                return '';
            }

            if ($this->params->get('replaceImports', '0')) {
                $this->replaceImports($cssComponent);
            } else {
                $this->cacheObject->addImports($cssComponent->render());
            }

            return '';
        }

        return $cssComponent->render();
    }

    protected function supportedCssComponents(): array
    {
        return [
            FontFaceAtRule::class,
            ImportAtRule::class,
            CharsetAtRule::class,
            KeyFrames::class
        ];
    }

    private function resolveImportUri(ImportAtRule $cssComponent): void
    {
        $correctUrlObj = $this->getContainer()->get(CorrectUrls::class)
        ->setContext('import')
        ->setCssInfo($this->getCssInfo());

        if (!$this->params->get('replaceImports', '0')) {
            $correctUrlObj->setHandlingCriticalCss(true);
        }

        $cssComponent->setUri(
            $correctUrlObj->processUri($cssComponent->getUri())
        );

        $this->cacheObject->merge($correctUrlObj->getCacheObject());
    }

    private function handleGoogleFonts(ImportAtRule $cssComponent): void
    {
        if ($this->params->get('pro_optimizeFonts_enable', '0')) {
            //We have to add Gfonts here so this info will be cached
            $this->cacheObject->addGFonts([
                'url' => $cssComponent->getUri(),
                'media' => $cssComponent->getMediaQueriesList(),
            ]);
        } else {
            $this->cacheObject->addImports($cssComponent->render());
        }
    }

    private function replaceImports(ImportAtRule $cssComponent): void
    {
        if (!$this->validateImportUri($cssComponent)) {
            $this->cacheObject->addImports($cssComponent->render());
        }

        try {
            $combiner = $this->getContainer()->get(Combiner::class);
            $this->cacheObject->setImportedContents($combiner->combineFiles([new FileInfo($cssComponent)]));
        } catch (Exception | ExceptionInterface) {
            $this->cacheObject->addImports($cssComponent->render());
        }
    }

    private function validateImportUri(ImportAtRule $cssComponent): bool
    {
        $oFilesManager = $this->getContainer()->get(FilesManager::class);
        $uri = $cssComponent->getUri();

        if (
            (string)$uri == ''
            || ($uri->getScheme() == 'https' && !extension_loaded('openssl'))
        ) {
            $this->cacheObject->addImports($cssComponent->render());

            return false;
        }

        if ($oFilesManager->isDuplicated($uri)) {
            return false;
        }

        return true;
    }

    private function resolveFontFaceUri(FontFaceAtRule $cssComponent): void
    {
        $correctUrlObj = $this->getContainer()->get(CorrectUrls::class)
            ->setContext('font-face')
            ->setCssInfo($this->getCssInfo())
            ->setHandlingCriticalCss(false);

        $cssComponent->modifyCssUrls($correctUrlObj);
    }

    private function implementFontDisplayPolicy(FontFaceAtRule $cssComponent): void
    {
        if (!$cssComponent->hasDescriptor('font-display')) {
            $cssComponent->setFontDisplay('swap');
        } elseif ($this->params->get('pro_force_swap_policy', '1')) {
            $cssComponent->setFontDisplay('swap');
        }
    }

    private function preprocessOptimizeCssDelivery(FontFaceAtRule|KeyFrames $cssComponent): string
    {
        if ($this->params->get('optimizeCssDelivery_enable', '0')) {
            $this->addToSecondaryCss($cssComponent);

            return '';
        }

        return $cssComponent->render();
    }
}
