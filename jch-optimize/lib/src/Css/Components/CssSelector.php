<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Css\Components;

use CodeAlfa\Css2Xpath\Selector\AttributeSelector;
use CodeAlfa\Css2Xpath\Selector\ClassSelector;
use CodeAlfa\Css2Xpath\Selector\IdSelector;
use CodeAlfa\Css2Xpath\Selector\PseudoSelector;
use CodeAlfa\Css2Xpath\Selector\TypeSelector;
use JchOptimize\Core\Css\CssComponents;
use JchOptimize\Core\Css\CssSelectorFactory;
use SplObjectStorage;

use function is_string;
use function preg_match;

class CssSelector extends \CodeAlfa\Css2Xpath\Selector\CssSelector implements CssComponents
{
    public static function load(string $css): static
    {
        $selectorFactory = new CssSelectorFactory();

        return parent::create($selectorFactory, $css);
    }

    public function render(): string
    {
        return $this->renderTypeSelector()
            . $this->renderIdSelector()
            . $this->renderClassSelector()
            . $this->renderAttributeSelector()
            . $this->renderPseudoSelector()
            . $this->renderDescendant();
    }

    private function renderTypeSelector(): string
    {
        if (($type = $this->getType()) instanceof TypeSelector) {
            return $type->getName();
        }

        return '';
    }

    private function renderIdSelector(): string
    {
        if (($id = $this->getId()) instanceof IdSelector) {
            return "#{$id->getName()}";
        }

        return '';
    }

    private function renderClassSelector(): string
    {
        $css = '';

        foreach ($this->getClasses() as $class) {
            $css .= ".{$class->getName()}";
        }

        return $css;
    }

    private function renderAttributeSelector(): string
    {
        $css = '';

        foreach ($this->getAttributes() as $attribute) {
            $css .= "[{$attribute->getName()}{$attribute->getOperator()}{$attribute->getValue()}]";
        }

        return $css;
    }

    private function renderPseudoSelector(): string
    {
        $css = '';

        foreach ($this->getPseudoSelectors() as $pseudoSelector) {
            $css .= "{$this->getPseudoPrefix($pseudoSelector)}{$pseudoSelector->getName()}";

            if (($selectorList = $pseudoSelector->getSelectorList()) instanceof CssSelectorList) {
                $css .= "({$selectorList->render()})";
            }
        }

        return $css;
    }

    private function getPseudoPrefix(PseudoSelector $pseudoSelector): string
    {
        return preg_match(
            "#before|after|first-(?:line|letter)#i",
            $pseudoSelector->getName()
        ) ? '::' : $pseudoSelector->getPrefix();
    }

    private function renderDescendant(): string
    {
        if (is_string($this->descendant)) {
            return $this->combinator . $this->descendant;
        }

        if ($this->descendant instanceof CssSelector) {
            return $this->combinator . $this->descendant->render();
        }

        return '';
    }

    public function addClass(string $class): static
    {
        if (($descendant = $this->getDescendant()) instanceof CssSelector) {
            $descendant->addClass($class);

            return $this;
        }

        foreach ($this->pseudoSelectors as $pseudoSelector) {
            if (($pseudoSelectorList = $pseudoSelector->getSelectorList()) instanceof CssSelectorList) {
                $pseudoSelectorList->addClass($class);

                return $this;
            }
        }

        $this->classes->attach(new ClassSelector($class));

        return $this;
    }

    public function hasNonFunctionalPseudoSelector(): bool
    {
        if (($descendant = $this->getDescendant()) instanceof CssSelector) {
            return $descendant->hasNonFunctionalPseudoSelector();
        }

        foreach ($this->pseudoSelectors as $pseudoSelector) {
            if (!$pseudoSelector->getSelectorList() instanceof CssSelectorList) {
                return true;
            }
        }

        return false;
    }

    public function removeLastDescendantNonFunctionalPseudoSelectors(): static
    {
        $descendant = $this->getDescendant();

        if ($descendant instanceof CssSelector) {
            $descendant->removeLastDescendantNonFunctionalPseudoSelectors();

            return $this;
        }

        foreach ($this->pseudoSelectors as $pseudoSelector) {
            if (!$pseudoSelector->getSelectorList() instanceof CssSelectorList) {
                $this->pseudoSelectors->detach($pseudoSelector);
            }
        }

        return $this;
    }

    public function __clone()
    {
        if ($this->type instanceof TypeSelector) {
            $this->type = clone $this->type;
        }

        if ($this->id instanceof IdSelector) {
            $this->id = clone $this->id;
        }

        /** @var SplObjectStorage<ClassSelector, null> $classes */
        $classes = new SplObjectStorage();
        foreach ($this->classes as $class) {
            $classes->attach(clone $class);
        }
        $this->classes = $classes;

        /** @var SplObjectStorage<AttributeSelector, null> $attributes */
        $attributes = new SplObjectStorage();
        foreach ($this->attributes as $attribute) {
            $attributes->attach(clone $attribute);
        }
        $this->attributes = $attributes;

        /** @var SplObjectStorage<PseudoSelector, null> $pseudoSelectors */
        $pseudoSelectors = new SplObjectStorage();
        foreach ($this->pseudoSelectors as $pseudoSelector) {
            $pseudoSelectors->attach(clone $pseudoSelector);
        }
        $this->pseudoSelectors = $pseudoSelectors;

        if ($this->descendant instanceof CssSelector) {
            $this->descendant = $this->descendant->render();
        }
    }

    public function renderLastDescendantNonFunctionalPseudoSelector($combinator = ''): string
    {
        $descendant = $this->getDescendant();

        if ($descendant instanceof CssSelector) {
            return $descendant->renderLastDescendantNonFunctionalPseudoSelector($this->combinator);
        }

        $css = '';

        if (
            $this->getType() === null
            && $this->getId() === null
            && $this->getClasses()->count() === 0
            && $this->getAttributes()->count() === 0
        ) {
            $css = $combinator;
        }

        /** @var PseudoSelector $pseudoSelector */
        foreach ($this->pseudoSelectors as $pseudoSelector) {
            if (!$pseudoSelector->getSelectorList() instanceof CssSelectorList) {
                $css .= "{$this->getPseudoPrefix($pseudoSelector)}{$pseudoSelector->getName()}";
            }
        }

        return $css;
    }
}
