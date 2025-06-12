<?php

namespace CodeAlfa\Css2Xpath;

use CodeAlfa\Css2Xpath\Selector\AttributeSelector;
use CodeAlfa\Css2Xpath\Selector\ClassSelector;
use CodeAlfa\Css2Xpath\Selector\CssSelector;
use CodeAlfa\Css2Xpath\Selector\CssSelectorList;
use CodeAlfa\Css2Xpath\Selector\IdSelector;
use CodeAlfa\Css2Xpath\Selector\PseudoSelector;
use CodeAlfa\Css2Xpath\Selector\TypeSelector;

interface SelectorFactoryInterface
{
    public function createCssSelectorList(\CodeAlfa\Css2Xpath\SelectorFactoryInterface $selectorFactory, string $cssSelectorList): CssSelectorList;
    public function createCssSelector(\CodeAlfa\Css2Xpath\SelectorFactoryInterface $selectorFactory, string $cssSelector): CssSelector;
    public function createTypeSelector(string $name, ?string $namespace = null): TypeSelector;
    public function createIdSelector(string $name): IdSelector;
    public function createClassSelector(string $name): ClassSelector;
    public function createAttributeSelector(string $name, string $value = '', string $operator = '', ?string $namespace = null): AttributeSelector;
    public function createPseudoSelector(\CodeAlfa\Css2Xpath\SelectorFactoryInterface $selectorFactory, string $name, string $prefix, ?string $selectorList = null, string $modifier = ''): PseudoSelector;
}
