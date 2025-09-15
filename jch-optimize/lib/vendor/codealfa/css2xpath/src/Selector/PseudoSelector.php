<?php

namespace CodeAlfa\Css2Xpath\Selector;

use CodeAlfa\Css2Xpath\SelectorFactoryInterface;

class PseudoSelector extends \CodeAlfa\Css2Xpath\Selector\AbstractSelector
{
    protected SelectorFactoryInterface $selectorFactory;
    protected string $prefix;
    protected string $name;
    protected \CodeAlfa\Css2Xpath\Selector\CssSelectorList|string|null $selectorList;
    protected string $modifier;
    public function __construct(SelectorFactoryInterface $selectorFactory, string $name, string $prefix, ?string $selectorList, string $modifier = '')
    {
        $this->selectorFactory = $selectorFactory;
        $this->name = $name;
        $this->prefix = $prefix;
        $this->selectorList = $selectorList;
        $this->modifier = $modifier;
    }
    public function getName(): string
    {
        return $this->name;
    }
    public function render(): string
    {
        return match ($this->getName()) {
            'enabled' => "[@enabled]",
            'disabled' => "[@disabled]",
            'read-only' => "[@readonly]",
            'read-write' => "[@readwrite]",
            'checked' => "[@selected or @checked]",
            'required' => "[@required]",
            'root' => "/ancestor::*[last()]",
            'empty' => "[not(*) and not(normalize-space())]",
            'first-child' => "[not(preceding-sibling::*)]",
            'last-child' => "[not(following-sibling::*)]",
            'only-child' => "[not(preceding-sibling::*) and not(following-sibling::*)]",
            'first-of-type' => "[1]",
            'last-of-type' => "[last()]",
            'only-of-type' => "[not(preceding-sibling::*[name()=name(self::node())])" . " and not(following-sibling::*[name()=name(self::node())])]",
            'not' => "[not({$this->transformNotSelectorList($this->renderSelectorList())})]",
            'has' => "[count({$this->renderSelectorList()}) > 0]",
            default => '',
        };
    }
    public function getPrefix(): string
    {
        return $this->prefix;
    }
    public function getSelectorList(): ?\CodeAlfa\Css2Xpath\Selector\CssSelectorList
    {
        if (is_string($this->selectorList)) {
            $this->selectorList = $this->selectorFactory->createCssSelectorList($this->selectorFactory, $this->selectorList);
        }
        return $this->selectorList;
    }
    protected function transformNotSelectorList(string $xpath): string
    {
        return preg_replace(['#^descendant-or-self::\*#', '#^descendant-or-self::#'], ['self::node()', ''], $xpath);
    }
    protected function renderSelectorList(): string
    {
        return (string) $this->getSelectorList()?->render();
    }
    public function getModifier(): string
    {
        return $this->modifier;
    }
}
