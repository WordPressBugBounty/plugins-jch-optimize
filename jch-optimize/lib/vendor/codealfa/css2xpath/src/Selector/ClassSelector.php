<?php

namespace CodeAlfa\Css2Xpath\Selector;

class ClassSelector extends \CodeAlfa\Css2Xpath\Selector\AbstractSelector
{
    protected string $name;
    public function __construct(string $name)
    {
        $this->name = $this->cssStripSlash($name);
    }
    public function render(): string
    {
        $delimiter = $this->getDelimiter($this->getName());
        return "[@class and contains(concat(\" \", normalize-space(@class), \" \"), " . "{$delimiter} {$this->getName()} {$delimiter})]";
    }
    public function getName(): string
    {
        return $this->name;
    }
}
