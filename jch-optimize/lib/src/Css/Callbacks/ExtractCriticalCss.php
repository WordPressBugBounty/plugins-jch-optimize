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
use JchOptimize\Core\Css\Callbacks\Dependencies\CriticalCssDependencies;
use JchOptimize\Core\Css\Components\CssRule;
use JchOptimize\Core\Css\CssComponents;
use JchOptimize\Core\Css\Xpath\CssSelector as CssSelectorXpath;
use JchOptimize\Core\Css\Xpath\SelectorFactory;
use JchOptimize\Core\FeatureHelpers\DynamicSelectors;
use JchOptimize\Core\Registry;

use function defined;
use function in_array;
use function preg_split;
use function str_contains;

defined('_JCH_EXEC') or die('Restricted access');

class ExtractCriticalCss extends AbstractCallback
{
    private SelectorFactoryInterface $selectorFactory;

    public function getDependencies(): CriticalCssDependencies
    {
        return $this->dependencies;
    }

    public function __construct(
        Container $container,
        Registry $params,
        private CriticalCssDependencies $dependencies,
        private DynamicSelectors $dynamicSelectors
    ) {
        parent::__construct($container, $params);

        $this->selectorFactory = new SelectorFactory();
    }

    protected function internalProcessMatches(CssComponents $cssComponent): string
    {
        if (!$cssComponent instanceof CssRule) {
            return $cssComponent->render();
        }

        $selectorList = $cssComponent->getSelectorList();

        if (
            ($this->dependencies->selectorListCache[$selectorList]
                ??= $this->evaluateSelectorLists($cssComponent)) === true
        ) {
            $this->modifyUrls($cssComponent, true);
            $this->addToSecondaryCss($cssComponent);
        } else {
            $this->modifyUrls($cssComponent, false);
        }

        return $cssComponent->render();
    }

    protected function evaluateSelectorLists(CssRule $cssComponent): bool
    {
        $selectorList = strtolower($cssComponent->getSelectorList());

        if ($this->dynamicSelectors->getDynamicSelectors($selectorList)) {
            return true;
        }

        $selectors = preg_split("#\s*+,\s*+#", $selectorList);

        foreach ($selectors as $selector) {
            $cssSelectorXpath = CssSelectorXpath::create($this->selectorFactory, $selector);

            if (!$cssSelectorXpath->isValid()) {
                return false;
            }

            if (
                ($this->dependencies->selectorCache[$selector]
                    ??= $this->evaluateSelectorXpath($cssSelectorXpath)) === true
            ) {
                return true;
            }
        }

        return false;
    }

    protected function evaluateSelectorXpath(CssSelectorXpath $cssSelectorXpath): bool
    {
        //Check CSS selector chain against HTMl above the fold to find a match
        if (!$this->checkCssAgainstHtml($cssSelectorXpath, $this->dependencies->getHtmlAboveFold())) {
            return false;
        }

        if ($cssSelectorXpath->hasPseudoClass(['hover', 'active', 'focus', 'focus-visible', 'focus-within'])) {
            return false;
        }

        $xPath = $cssSelectorXpath->render();

        if (str_contains($xPath, 'descendant-or-self::*[1]')) {
            return true;
        }

        $element = $this->dependencies->getDOMXPath()->query($xPath);

        if ($element instanceof DOMNodeList && $element->length) {
            return true;
        }

        return false;
    }

    protected function checkCssAgainstHtml(CssSelectorXpath $selector, string $html): bool
    {
        if (
            !empty($type = $selector->getType())
            && !in_array($type->getName(), ['*', 'tbody', 'thead', 'tfoot'])
            && !preg_match("#<{$type->getName()}\b(?:\s|>)#", $html)
        ) {
            return false;
        }

        if (
            !empty($id = $selector->getId())
            && !str_contains($html, "{$id->getName()}")
        ) {
            return false;
        }

        foreach ($selector->getClasses() as $class) {
            if (!str_contains($html, "{$class->getName()}")) {
                return false;
            }
        }

        foreach ($selector->getAttributes() as $attribute) {
            if (
                !empty($attribute->getName())
                && (!str_contains($html, "{$attribute->getValue()}")
                    || !str_contains($html, " {$attribute->getName()}="))
            ) {
                return false;
            }

            if (!str_contains($html, "{$attribute->getName()}")) {
                return false;
            }
        }

        if (($descendant = $selector->getDescendant()) instanceof CssSelectorXpath) {
            return $this->checkCssAgainstHtml($descendant, $html);
        }

        return true;
    }

    protected function supportedCssComponents(): array
    {
        return [
            CssRule::class,
        ];
    }

    private function modifyUrls(CssRule $cssComponent, bool $isCriticalCss): void
    {
        if (str_contains($cssComponent->getDeclarationList(), "url(")) {
            $correctUrlObj = $this->getContainer()->get(CorrectUrls::class)
                ->setCssInfo($this->getCssInfo())
                ->setHandlingCriticalCss($isCriticalCss);
            $correctUrlObj->processCssRule($cssComponent);
            $this->cacheObject->merge($correctUrlObj->getCacheObject());
        }
    }
}
