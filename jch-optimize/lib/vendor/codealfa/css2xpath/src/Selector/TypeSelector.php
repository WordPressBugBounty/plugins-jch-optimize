<?php

namespace CodeAlfa\Css2Xpath\Selector;

class TypeSelector extends \CodeAlfa\Css2Xpath\Selector\AbstractSelector
{
    protected ?string $namespace;
    protected string $name;
    public function __construct(string $name, ?string $namespace = null)
    {
        $this->name = $name;
        $this->namespace = $namespace;
    }
    public function render(): string
    {
        $namespace = $this->getNamespace() !== null ? "{$this->getNamespace()}:" : '';
        return "{$namespace}{$this->getName()}";
    }
    public function getNamespace(): ?string
    {
        return $this->namespace;
    }
    public function getName(): string
    {
        return $this->name;
    }
}
