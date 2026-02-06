<?php

namespace CodeAlfa\Css2Xpath;

use CodeAlfa\Css2Xpath\Selector\AttributeSelector;
use CodeAlfa\Css2Xpath\Selector\ClassSelector;
use CodeAlfa\Css2Xpath\Selector\CssSelector;
use CodeAlfa\Css2Xpath\Selector\CssSelectorList;
use CodeAlfa\Css2Xpath\Selector\IdSelector;
use CodeAlfa\Css2Xpath\Selector\PseudoClassSelector;
use CodeAlfa\Css2Xpath\Selector\PseudoElementSelector;
use CodeAlfa\Css2Xpath\Selector\TypeSelector;

class SelectorFactory implements \CodeAlfa\Css2Xpath\SelectorFactoryInterface
{
    public function createCssSelectorList(\CodeAlfa\Css2Xpath\SelectorFactoryInterface $selectorFactory, string $cssSelectorList): CssSelectorList
    {
        return CssSelectorList::create($selectorFactory, $cssSelectorList);
    }
    public function createCssSelector(\CodeAlfa\Css2Xpath\SelectorFactoryInterface $selectorFactory, string $cssSelector): CssSelector
    {
        return CssSelector::create($selectorFactory, $cssSelector);
    }
    public function createTypeSelector(string $name, ?string $namespace = null): TypeSelector
    {
        return new TypeSelector($name, $namespace);
    }
    public function createIdSelector(string $name): IdSelector
    {
        return new IdSelector($name);
    }
    public function createClassSelector(string $name): ClassSelector
    {
        return new ClassSelector($name);
    }
    public function createAttributeSelector(string $name, string $value = '', string $operator = '', ?string $namespace = null): AttributeSelector
    {
        return new AttributeSelector($name, $value, $operator, $namespace);
    }
    public function createPseudoClassSelector(\CodeAlfa\Css2Xpath\SelectorFactoryInterface $selectorFactory, string $name, ?string $selectorList = null, string $modifier = '', ?string $elementName = null): PseudoClassSelector
    {
        return new PseudoClassSelector($selectorFactory, $name, $selectorList, $modifier, $elementName);
    }
    public function createPseudoElementSelector(\CodeAlfa\Css2Xpath\SelectorFactoryInterface $selectorFactory, string $name): PseudoElementSelector
    {
        return new PseudoElementSelector($selectorFactory, $name);
    }
}
