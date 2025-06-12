<?php

namespace CodeAlfa\Css2Xpath\Selector;

class AttributeSelector extends \CodeAlfa\Css2Xpath\Selector\AbstractSelector
{
    protected ?string $namespace;
    protected string $name;
    protected string $operator;
    protected string $value;
    public function __construct(string $name, string $value = '', string $operator = '', ?string $namespace = null)
    {
        $this->name = $name;
        $this->value = $this->cssStripSlash($value);
        $this->operator = $operator;
        $this->namespace = $namespace;
    }
    public function render(): string
    {
        $attrName = $this->getNamespace() !== null ? "{$this->getNamespace()}:{$this->getName()}" : "{$this->getName()}";
        $delim = $this->getDelimiter($this->getValue());
        $attrExpression = match ($this->getOperator()) {
            '=' => "@{$attrName}={$delim}{$this->getValue()}{$delim}",
            '~=' => "contains(concat(\" \",normalize-space(@{$attrName}),\" \"),{$delim} {$this->getValue()} {$delim})",
            '|=' => "@{$attrName}={$delim}{$this->getValue()}{$delim}" . " or starts-with(@{$attrName},concat({$delim}{$this->getValue()}{$delim},\"-\"))",
            '^=' => "starts-with(@{$attrName}, {$delim}{$this->getValue()}{$delim})",
            '$=' => "substring(@{$attrName},string-length(@{$attrName})" . "-(string-length({$delim}{$this->getValue()}{$delim})-1))={$delim}{$this->getValue()}{$delim}",
            '*=' => "contains(@{$attrName}, {$delim}{$this->getValue()}{$delim})",
            default => "@{$attrName}",
        };
        return "[{$attrExpression}]";
    }
    public function getNamespace(): ?string
    {
        return $this->namespace;
    }
    public function getName(): string
    {
        return $this->name;
    }
    public function getOperator(): string
    {
        return $this->operator;
    }
    public function getValue(): string
    {
        return $this->value;
    }
}
