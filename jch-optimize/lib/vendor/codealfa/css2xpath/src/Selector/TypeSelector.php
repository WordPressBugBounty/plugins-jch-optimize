<?php

namespace CodeAlfa\Css2Xpath\Selector;

class TypeSelector extends \CodeAlfa\Css2Xpath\Selector\AbstractSelector
{
    public function __construct(protected string $name, protected ?string $namespace = null)
    {
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
