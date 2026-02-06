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

use CodeAlfa\Css2Xpath\Collections\AttributeCollection;
use CodeAlfa\Css2Xpath\Collections\ClassCollection;
use CodeAlfa\Css2Xpath\Collections\PseudoClassCollection;
use CodeAlfa\Css2Xpath\Selector\ClassSelector;
use CodeAlfa\Css2Xpath\Selector\IdSelector;
use CodeAlfa\Css2Xpath\Selector\PseudoElementSelector;
use CodeAlfa\Css2Xpath\Selector\TypeSelector;
use JchOptimize\Core\Css\CssComponents;
use JchOptimize\Core\Css\CssSelectorFactory;

use function is_string;

class CssSelector extends \CodeAlfa\Css2Xpath\Selector\CssSelector implements CssComponents
{
    protected ?ClassSelector $appendedClass = null;

    public static function load(string $css): static
    {
        $selectorFactory = new CssSelectorFactory();

        return parent::create($selectorFactory, $css);
    }

    public function render(?string $axis = null): string
    {
        return $this->renderTypeSelector()
            . $this->renderIdSelector()
            . $this->renderClassSelector()
            . $this->renderAttributeSelector()
            . $this->renderPseudoClassSelector()
            . $this->renderAppendedClass()
            . $this->renderPseudoElementSelector()
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
            $attributeValue = $attribute->getValue() ? "\"{$attribute->getValue()}\"" : '';
            $css .= "[{$attribute->getName()}{$attribute->getOperator()}{$attributeValue}]";
        }

        return $css;
    }

    private function renderPseudoClassSelector(): string
    {
        $css = '';

        foreach ($this->getPseudoClasses() as $pseudoClass) {
            $css .= ":{$pseudoClass->getName()}";

            if (($selectorList = $pseudoClass->getSelectorList()) instanceof CssSelectorList) {
                $css .= "({$selectorList->render()})";
            }
        }

        return $css;
    }

    private function renderAppendedClass(): string
    {
        if ($this->appendedClass instanceof ClassSelector) {
            return ".{$this->appendedClass->getName()}";
        }

        return '';
    }

    private function renderPseudoElementSelector(): string
    {
        if (($pseudoElement = $this->getPseudoElement()) instanceof PseudoElementSelector) {
            return "::{$pseudoElement->getName()}";
        }

        return '';
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

    public function appendClass(string $class): static
    {
        if (($descendant = $this->getDescendant()) instanceof CssSelector) {
            $descendant->appendClass($class);

            return $this;
        }

        $this->appendedClass = new ClassSelector($class);

        return $this;
    }

    public function hasPseudoElement(): bool
    {
        if (($descendant = $this->getDescendant()) instanceof CssSelector) {
            return $descendant->hasPseudoElement();
        }

        if ($this->pseudoElement instanceof PseudoElementSelector) {
            return true;
        }

        return false;
    }

    public function removePseudoElement(): static
    {
        $descendant = $this->getDescendant();

        if ($descendant instanceof CssSelector) {
            $descendant->removePseudoElement();

            return $this;
        }

        $this->pseudoElement = null;

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

        if ($this->classes instanceof ClassCollection) {
            $classes = new ClassCollection();
            foreach ($this->classes as $class) {
                $classes->offsetSet(clone $class);
            }
            $this->classes = $classes;
        }

        if ($this->attributes instanceof AttributeCollection) {
            $attributes = new AttributeCollection();
            foreach ($this->attributes as $attribute) {
                $attributes->offsetSet(clone $attribute);
            }
            $this->attributes = $attributes;
        }

        if ($this->pseudoClasses instanceof PseudoClassCollection) {
            $pseudoClasses = new PseudoClassCollection();
            foreach ($this->pseudoClasses as $pseudoSelector) {
                $pseudoClasses->offsetSet(clone $pseudoSelector);
            }
            $this->pseudoClasses = $pseudoClasses;
        }

        if ($this->appendedClass instanceof ClassSelector) {
            $this->appendedClass = clone $this->appendedClass;
        }

        if ($this->pseudoElement instanceof PseudoElementSelector) {
            $this->pseudoElement = clone $this->pseudoElement;
        }

        if ($this->descendant instanceof CssSelector) {
            $this->descendant = $this->descendant->render();
        }
    }

    public function renderPseudoElement(string $combinator = ''): string
    {
        $descendant = $this->getDescendant();

        if ($descendant instanceof CssSelector) {
            return $descendant->renderPseudoElement($this->combinator);
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

        if (($pseudoElement = $this->getPseudoElement()) instanceof PseudoElementSelector) {
            $css .= "::{$pseudoElement->getName()}";
        }

        return $css;
    }
}
