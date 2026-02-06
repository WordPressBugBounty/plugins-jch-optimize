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

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use CodeAlfa\Css2Xpath\SelectorFactoryInterface;
use DOMNodeList;
use JchOptimize\Core\CacheObject;
use JchOptimize\Core\Css\Callbacks\Dependencies\CriticalCssDependencies;
use JchOptimize\Core\Css\Components\CssRule;
use JchOptimize\Core\Css\CssComponents;
use JchOptimize\Core\Css\Xpath\CssSelector as CssSelectorXpath;
use JchOptimize\Core\Css\Xpath\SelectorFactory;
use JchOptimize\Core\FeatureHelpers\DynamicSelectors;
use JchOptimize\Core\Registry;

use function defined;
use function in_array;
use function microtime;
use function preg_split;
use function str_contains;

defined('_JCH_EXEC') or die('Restricted access');

class ExtractCriticalCss extends AbstractCallback
{
    private bool $correctUrlsConfigured = false;

    private ?float $budgetSeconds = null;

    private float $startedAt = 0.0;

    private bool $budgetExceeded = false;

    public function getDependencies(): CriticalCssDependencies
    {
        return $this->dependencies;
    }

    public function __construct(
        Container $container,
        Registry $params,
        private CriticalCssDependencies $dependencies,
        private DynamicSelectors $dynamicSelectors,
        private CorrectUrls $correctUrls,
        private ?SelectorFactoryInterface $selectorFactory = null
    ) {
        parent::__construct($container, $params);

        $this->selectorFactory = $selectorFactory ?? new SelectorFactory();
    }

    public function initBudget(?float $budgetSeconds): void
    {
        $this->budgetSeconds = $budgetSeconds;
        $this->startedAt = microtime(true);
        $this->budgetExceeded = false;
    }

    private function isOutOfBudget(): bool
    {
        if ($this->budgetSeconds === null) {
            return false;
        }

        return (microtime(true) - $this->startedAt) > $this->budgetSeconds;
    }

    protected function internalProcessMatches(CssComponents $cssComponent): string
    {
        if (!$cssComponent instanceof CssRule) {
            return $cssComponent->render();
        }

        $profiler = $this->dependencies->getProfiler();

        $p = 'internal_process_matches';
        $profiler?->start($p);
        $selectorList = $cssComponent->getSelectorList();
        $selectorListKey = $this->normalizeSelectorList($selectorList);

        if ($this->getCssInfo()->isAboveFold() === true) {
            $this->dependencies->selectorListCache[$selectorListKey] = true;
        }

        if (
            ($this->dependencies->selectorListCache[$selectorListKey]
                ??= $this->evaluateSelectorLists($cssComponent)) === true
        ) {
            $this->modifyUrls($cssComponent, true);

            if ($this->isStaticSelectorList($selectorListKey)) {
                $this->addToSecondaryCss($cssComponent);
            }
            $this->addToTertiaryCss($cssComponent);
        } else {
            $this->modifyUrls($cssComponent, false);
        }
        $profiler?->stop($p);

        return $cssComponent->render();
    }

    protected function evaluateSelectorLists(CssRule $cssComponent): bool
    {
        $profiler = $this->dependencies->getProfiler();
        $selectorList = $cssComponent->getSelectorList();
        $selectorListKey = $this->normalizeSelectorList($selectorList);

        $s = 'selector_split';
        $profiler?->start($s);
        $selectors = preg_split("#(?>[^,(]++|\([^)]*+\))*?\K(?:,|$)#", $selectorList, 0, PREG_SPLIT_NO_EMPTY);
        $profiler?->stop($s);

        foreach ($selectors as $selector) {
            $hasPseudoElement = false;
            if (
                ($this->dependencies->selectorCache[$this->normalizeSelectorList($selector)]
                    ??= $this->evaluateSelector($selector, $hasPseudoElement)) === true
            ) {
                if (!$hasPseudoElement) {
                    $this->dependencies->criticalTypeBySelectorListCache[$selectorListKey] = 'static';
                } else {
                    $this->dependencies->criticalTypeBySelectorListCache[$selectorListKey] = 'dynamic';
                }

                return true;
            }
        }

        $d = 'dynamic_tokens';
        $profiler?->start($d);
        $hasDynamicToken = ($this->dynamicSelectors->ruleHasDynamicToken($selectorList) === true);
        $profiler?->stop($d);

        if ($hasDynamicToken) {
            $this->dependencies->criticalTypeBySelectorListCache[$selectorListKey] = 'dynamic';

            return true;
        }

        return false;
    }

    protected function evaluateSelector(string $selector, bool &$hasPseudoElement): bool
    {
        $profiler = $this->dependencies->getProfiler();

        $s = 'selector_create';
        $profiler?->start($s);
        $cssSelectorXpath = CssSelectorXpath::create($this->selectorFactory, $selector);
        $profiler?->stop($s);

        if (!$cssSelectorXpath->isValid()) {
            return false;
        }

        $r = 'xpath_render';
        $profiler?->start($r);
        $xPath = $cssSelectorXpath->renderFirstPerBranch();
        $profiler?->stop($r);

        //Check CSS selector chain against HTMl above the fold to find a match
        $c = 'check_dom';
        $profiler?->start($c);
        $candidate = $this->checkCssAgainstDom($cssSelectorXpath);
        $profiler?->stop($c);

        if (!$candidate) {
            return false;
        }

        if ($cssSelectorXpath->hasPseudoElement()) {
            $hasPseudoElement = true;

            return true;
        }

        $x = 'xpath_query';
        $profiler?->start($x);
        $element = $this->dependencies->getDOMXPath()->query($xPath);
        $profiler?->stop($x);

        return $element instanceof DOMNodeList && $element->length > 0;
    }

    protected function checkCssAgainstDom(CssSelectorXpath $selector): bool
    {
        $deps = $this->dependencies;

        if (
            !empty($type = $selector->getType())
            && !in_array($type->getName(), ['*', 'tbody', 'thead', 'tfoot'], true)
            && !$deps->hasTag($type->getName())
        ) {
            return false;
        }

        if (!empty($id = $selector->getId()) && !$deps->hasId($id->getName())) {
            return false;
        }

        foreach ($selector->getClasses() as $class) {
            if (!$deps->hasClass($class->getName())) {
                return false;
            }
        }

        foreach ($selector->getAttributes() as $attribute) {
            $name = $attribute->getName();
            $value = $attribute->getValue();

            if ($value !== '') {
                if (!$deps->hasAttrValue($name, $value)) {
                    return false;
                }
            } elseif (!$deps->hasAttrName($name)) {
                return false;
            }
        }

        if (($descendant = $selector->getDescendant()) instanceof CssSelectorXpath) {
            return $this->checkCssAgainstDom($descendant);
        }

        return true;
    }

    protected function supportedCssComponents(): array
    {
        return [
            CssRule::class,
        ];
    }

    private function normalizeSelectorList(string $selectorList): string
    {
        $profiler = $this->dependencies->getProfiler();
        $profiler?->start($normal = 'selector_normalize');
        $normalized = preg_replace('#\s*([,>+~])\s*#', '\1', strtolower($selectorList));
        $profiler?->stop($normal);

        return $normalized;
    }

    private function isStaticSelectorList(string $selectorListKey): bool
    {
        return ($this->dependencies->criticalTypeBySelectorListCache[$selectorListKey] ?? null) === 'static';
    }

    private function modifyUrls(CssRule $cssComponent, bool $isCriticalCss): void
    {
        if (!str_contains($cssComponent->getDeclarationList(), "url(")) {
            return;
        }

        $profiler = $this->dependencies->getProfiler();

        $profiler?->start($modify = 'modify_urls');
        $this->getConfiguredCorrectUrls()
            ->setHandlingCriticalCss($isCriticalCss)
            ->processCssRule($cssComponent);
        $profiler?->stop($modify);
    }

    private function getConfiguredCorrectUrls(): CorrectUrls
    {
        if (!$this->correctUrlsConfigured) {
            $this->correctUrls->setCssInfo($this->getCssInfo());
        }

        return $this->correctUrls;
    }

    public function getMergedCacheObject(): CacheObject
    {
        $this->cacheObject->merge($this->correctUrls->getCacheObject());

        return $this->cacheObject;
    }
}
