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

namespace JchOptimize\Core\Css;

use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareTrait;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareTrait;
use JchOptimize\Core\CacheObject;
use JchOptimize\Core\Css\Callbacks\CorrectUrls;
use JchOptimize\Core\Css\Callbacks\ExtractCriticalCss;
use JchOptimize\Core\Css\Callbacks\FormatCss;
use JchOptimize\Core\Css\Callbacks\HandleAtRules;
use JchOptimize\Core\Css\Callbacks\PostProcessCriticalCss;
use JchOptimize\Core\Css\Sprite\Generator;
use JchOptimize\Core\Exception;
use JchOptimize\Core\Exception\PregErrorException;
use JchOptimize\Core\FileInfo;
use JchOptimize\Core\Platform\ProfilerInterface;
use JchOptimize\Core\Registry;
use JchOptimize\Core\SerializableTrait;
use Serializable;

use function class_exists;
use function defined;
use function function_exists;
use function mb_convert_encoding;
use function mb_detect_encoding;
use function str_replace;

defined('_JCH_EXEC') or die('Restricted access');

class CssProcessor implements LoggerAwareInterface, ContainerAwareInterface, Serializable
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;
    use SerializableTrait;

    private string $debugUrl = '';

    protected FileInfo|null $cssInfos = null;

    private CacheObject $cacheObj;

    private bool $isLastKey = false;

    public function __construct(
        private Registry $params,
        private CorrectUrls $correctUrlsCallback,
        private ExtractCriticalCss $extractCriticalCssCallback,
        private FormatCss $formatCssCallback,
        private HandleAtRules $handleAtRulesCallback,
        private PostProcessCriticalCss $postProcessCriticalCssCallback,
        private ProfilerInterface $profiler
    ) {
        $this->cacheObj = new CacheObject();
    }

    public function setCssInfos(FileInfo $cssInfo): void
    {
        $this->cssInfos = $cssInfo;
        $this->correctUrlsCallback->setCssInfo($cssInfo);
        $this->handleAtRulesCallback->setCssInfo($cssInfo);
        $this->extractCriticalCssCallback->setCssInfo($cssInfo);
        $this->debugUrl = $cssInfo->display();
    }

    public function setCacheObj(CacheObject $cacheObj): void
    {
        $this->cacheObj = $cacheObj;
    }

    public function getCacheObj(): CacheObject
    {
        return $this->cacheObj;
    }

    public function setCss(string $css): void
    {
        $this->cacheObj->setContents($this->removeZeroWidthNonBreakingSpace($css));
    }

    public function formatCss(): void
    {
        $oParser = new Parser();

        $bc = Parser::blockCommentToken();
        $cssRule = Parser::cssRuleToken();
        $regularAtRule = Parser::cssRegularAtRulesToken();
        $nestedAtRule = Parser::cssNestingAtRulesToken();

        $criteria = "(?!\s++|{$bc}|$cssRule|{$regularAtRule}|{$nestedAtRule})";

        $oSearchObject = new CssSearchObject();
        $oSearchObject->setCssMatchCriteria($criteria);
        $oSearchObject->setCssMatch(Parser::cssInvalidCssToken());
        $oParser->setCssSearchObject($oSearchObject);

        try {
            $this->cacheObj->setContents(
                $oParser->processMatchesWithCallback($this->cacheObj->getContents() . '}', $this->formatCssCallback)
            );
        } catch (Exception\PregErrorException $oException) {
            $this->logger?->error('FormatCss failed - ' . $this->debugUrl . ': ' . $oException->getMessage());
        }
    }

    /**
     * The path to the combined CSS files differs from the original path so relative paths to images in the files are
     * converted to absolute paths. This method is used again to preload assets found in the Critical CSS after Optimize
     * CSS Delivery is performed
     */
    public function processUrls(): void
    {
        if ($this->optimizeCssDeliveryEnabled()) {
            //Already processed
            return;
        }

        $oParser = new Parser();
        $cssRule = new CssSearchObject();
        $cssRule->setCssMatch(Parser::cssRuleToken());
        $oParser->setCssSearchObject($cssRule);

        $nestedAtRule = new CssSearchObject();
        $nestedAtRule->setCssMatch(Parser::cssNestingAtRulesToken());
        $oParser->setCssSearchObject($nestedAtRule);

        try {
            $this->correctUrlsCallback->setHandlingCriticalCss(false);
            $this->cacheObj->setContents(
                $oParser->processMatchesWithCallback($this->cacheObj->getContents(), $this->correctUrlsCallback)
            );

            $this->cacheObj->merge($this->correctUrlsCallback->getCacheObject());
        } catch (Exception\PregErrorException $oException) {
            $this->logger?->error($this->debugUrl . ': ' . $oException->getMessage());
        }
    }

    public function processAtRules(): void
    {
        $oParser = new Parser();
        $oParser->matchOnlyAtRules();

        $regularAtRule = new CssSearchObject();
        $regularAtRule->setCssMatchCriteria('(?=@(?:import|charset))');
        $regularAtRule->setCssMatch(Parser::cssRegularAtRulesToken());
        $oParser->setCssSearchObject($regularAtRule);

        $nestedAtRule = new CssSearchObject();
        $nestedAtRule->setCssMatchCriteria('(?=@(?:-[^-]++-)?(?:font-face|keyframes))');
        $nestedAtRule->setCssMatch(Parser::cssNestingAtRulesToken());
        $oParser->setCssSearchObject($nestedAtRule);

        try {
            $this->cacheObj->setContents(
                $this->cleanEmptyMedias(
                    $oParser->processMatchesWithCallback($this->cacheObj->getContents(), $this->handleAtRulesCallback)
                )
            );
            $this->cacheObj->setPotentialCriticalCssAtRules(
                $this->handleAtRulesCallback->getAndResetSecondaryCss()
            );
            $this->cacheObj->merge($this->handleAtRulesCallback->getCacheObject());
        } catch (Exception\PregErrorException $oException) {
            $this->logger?->error(
                'ProcessAtRules failed - ' . $this->debugUrl . ': ' . $oException->getMessage()
            );
        }

        $this->extractCriticalCssCallback->getDependencies()->addToPotentialCriticalCssAtRules(
            $this->cacheObj->getPotentialCriticalCssAtRules()
        );
    }

    /**
     * @throws PregErrorException
     */
    public function cleanEmptyMedias(string $css): string
    {
        $bc = Parser::blockCommentToken();
        $criteria = "(?=@(?>[^{};/]++|{$bc})++{\s*+})";

        $parser = new Parser();
        $parser->matchOnlyAtRules();
        $oCssEmptyMediaObject = new CssSearchObject();
        $oCssEmptyMediaObject->setCssMatchCriteria($criteria);
        $oCssEmptyMediaObject->setCssMatch(Parser::cssNestingAtRulesToken());

        $parser->setCssSearchObject($oCssEmptyMediaObject);

        return $parser->replaceMatches($css, '');
    }

    public function processConditionalAtRules(): void
    {
        if (($media = $this->getCssInfos()->getMedia()) !== '') {
            $this->cacheObj->setContents("@media {$media}{{$this->cacheObj->getContents()}}");
        }

        if (($supports = $this->getCssInfos()->getSupports()) !== '') {
            $this->cacheObj->setContents("@supports {$supports}{{$this->cacheObj->getContents()}}");
        }

        if (($layer = $this->getCssInfos()->getLayer()) !== '') {
            $this->cacheObj->setContents("@layer {$layer}{{$this->cacheObj->getContents()}}");
        }
    }

    public function optimizeCssDelivery(): void
    {
        if (!$this->optimizeCssDeliveryEnabled()) {
            return;
        }

        !JCH_DEBUG ?: $this->profiler->start('OptimizeCssDelivery');

        $oParser = new Parser();
        $oCssSearchObject = new CssSearchObject();
        $oCssSearchObject->setCssMatch(Parser::cssRuleToken());
        $oParser->setCssSearchObject($oCssSearchObject);

        $atRuleSearchObject = new CssSearchObject();
        $atRuleSearchObject->setCssMatch(Parser::cssNestingAtRulesToken());
        $oParser->setCssSearchObject($atRuleSearchObject);

        try {
            $this->cacheObj->setContents(
                $oParser->processMatchesWithCallback(
                    $this->cacheObj->getContents(),
                    $this->extractCriticalCssCallback
                )
            );
            $this->cacheObj->setCriticalCss(
                $this->cacheObj->getImports() .
                       $this->extractCriticalCssCallback->getAndResetSecondaryCss()
            );
        } catch (PregErrorException $e) {
            $this->logger?->error('Extracting Critical CSS failed: ' . $e->getMessage());
        }
        $this->cacheObj->merge($this->extractCriticalCssCallback->getCacheObject());
        $this->extractCriticalCssCallback->getDependencies()
            ->addToCriticalCssAggregate($this->cacheObj->getCriticalCss());

        !JCH_DEBUG ?: $this->profiler->stop('OptimizeCssDelivery', true);
    }

    private function optimizeCssDeliveryEnabled(): bool
    {
        return $this->params->get('optimizeCssDelivery_enable', '0')
            && class_exists('DOMDocument') && class_exists('DOMXPath');
    }

    public function postProcessCriticalCss(): void
    {
        if (!$this->isLastKey) {
            return;
        }

        $parser = new Parser();
        $atRuleSearchObject = new CssSearchObject();
        $atRuleSearchObject->setCssMatch(Parser::cssNestingAtRulesToken());
        $parser->setCssSearchObject($atRuleSearchObject);

        $criticalCssDependencies = $this->postProcessCriticalCssCallback->getDependencies();

        try {
            $this->cacheObj->appendCriticalCss(
                $parser->processMatchesWithCallback(
                    $criticalCssDependencies->getPotentialCriticalCssAtRules(),
                    $this->postProcessCriticalCssCallback
                )
            );
            $this->cacheObj->setBelowFoldFontsKeyFrame(
                $this->postProcessCriticalCssCallback->getAndResetSecondaryCss()
            );
            $this->cacheObj->merge($this->postProcessCriticalCssCallback->getCacheObject());
            $criticalCssDependencies->reset();
        } catch (PregErrorException $e) {
            $this->logger?->error('Post processing critical CSS failed: ' . $e->getMessage());
        }
    }

    public function processSprite(): void
    {
        if ($this->params->get('csg_enable', 0)) {
            try {
                /** @var Generator $oSpriteGenerator */
                $oSpriteGenerator = $this->getContainer()->get(Generator::class);
                $aSpriteCss = $oSpriteGenerator->getSprite($this->cacheObj->getContents());

                if (!empty($aSpriteCss) && !empty($aSpriteCss['needles']) && !empty($aSpriteCss['replacements'])) {
                    $this->cacheObj->setContents(
                        str_replace($aSpriteCss['needles'], $aSpriteCss['replacements'], $this->cacheObj->getContents())
                    );
                    $this->cacheObj->setCriticalCss(
                        str_replace(
                            $aSpriteCss['needles'],
                            $aSpriteCss['replacements'],
                            $this->cacheObj->getCriticalCss()
                        )
                    );
                }
            } catch (\Exception $ex) {
                $this->logger?->error($ex->getMessage());
            }
        }
    }

    public function setIsLastKey(bool $isLastKey): static
    {
        $this->isLastKey = $isLastKey;

        return $this;
    }

    public function getCssInfos(): FileInfo
    {
        if ($this->cssInfos !== null) {
            return $this->cssInfos;
        }

        throw new Exception\PropertyNotFoundException('Css Info not set');
    }

    private function detectEncoding(string $css): string|false
    {
        $possibleEncodings = [
            'UTF-8', 'ASCII', 'ISO-8859-1', 'Windows-1252', 'Windows-1251', 'JIS',
            'EUC-JP', 'EUC-KR', 'GB2312', 'Windows-1254', 'UTF-16',
            'ISO-8859-2', 'ISO-8859-3', 'ISO-8859-4', 'ISO-8859-5',
            'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8', 'ISO-8859-9', 'ISO-8859-10',
            'ISO-8859-13', 'ISO-8859-14', 'ISO-8859-15', 'ISO-8859-16',
        ];

        if (function_exists('mb_detect_encoding')) {
            return mb_detect_encoding($css, $possibleEncodings, true);
        }

        return false;
    }

    private function utf8Encode(string $css): string
    {
        try {
            $strEncoding = $this->detectEncoding($css);

            if ($strEncoding !== false && function_exists('mb_convert_encoding')) {
                $css = mb_convert_encoding($css, 'UTF-8', $strEncoding);

                if ($css === false) {
                    throw new Exception\RuntimeException('Character encoding conversion failed.');
                }

                $css = $this->removeZeroWidthNonBreakingSpace($css);
            }
        } catch (\Exception) {
        }

        return $css;
    }

    private function removeByteOrderMark(string $css): string
    {
        return str_replace("\xEF\xBB\xBF", '', $css);
    }

    private function removeZeroWidthNonBreakingSpace(string $css): string
    {
        $cleanedCss = preg_replace('#[\x{200B}-\x{200D}\x{FEFF}]#u', '', $css);

        if ($cleanedCss === null) {
            return $css;
        }

        return $cleanedCss;
    }
}
