<?php

namespace CodeAlfa\Css2Xpath\Selector;

use Stringable;

abstract class AbstractSelector implements \CodeAlfa\Css2Xpath\Selector\SelectorInterface, Stringable
{
    public function __toString(): string
    {
        return $this->render();
    }
    protected function cssStripSlash(string $identifier): string
    {
        return \preg_replace("#\\\\([^0-9a-fA-F\r\n])#", '\\1', $identifier);
    }
    protected function getDelimiter(string $identifier): string
    {
        return \str_contains($identifier, '"') ? "'" : '"';
    }
}
