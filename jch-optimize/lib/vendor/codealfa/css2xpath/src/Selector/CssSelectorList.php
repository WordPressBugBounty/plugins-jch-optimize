<?php

namespace CodeAlfa\Css2Xpath\Selector;

use CodeAlfa\Css2Xpath\Collections\CssSelectorCollection;
use CodeAlfa\Css2Xpath\SelectorFactoryInterface;

use function implode;
use function preg_split;

use const PREG_SPLIT_NO_EMPTY;

class CssSelectorList extends \CodeAlfa\Css2Xpath\Selector\AbstractSelector
{
    final public function __construct(protected CssSelectorCollection $selectors)
    {
    }
    public function render(?string $axis = null): string
    {
        $selectors = [];
        foreach ($this->selectors as $selector) {
            $selectors[] = $selector->render($axis);
        }
        return implode('|', $selectors);
    }
    public static function create(SelectorFactoryInterface $selectorFactory, string $css): static
    {
        $selectors = new CssSelectorCollection();
        $selectorStrings = preg_split('#(?:[^,(\s]++|(?<fn>\((?>[^()]++|(?&fn))*+\))|\s++)*?\K(?:\s*+,\s*+|$)+#', trim($css), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($selectorStrings as $selectorString) {
            $selectors->offsetSet($selectorFactory->createCssSelector($selectorFactory, $selectorString));
        }
        return new static($selectors);
    }
    public function getSelectors(): CssSelectorCollection
    {
        return $this->selectors;
    }
}
