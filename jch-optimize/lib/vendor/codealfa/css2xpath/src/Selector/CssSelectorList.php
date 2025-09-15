<?php

namespace CodeAlfa\Css2Xpath\Selector;

use CodeAlfa\Css2Xpath\SelectorFactoryInterface;
use SplObjectStorage;

use function implode;
use function preg_split;

use const PREG_SPLIT_NO_EMPTY;

class CssSelectorList extends \CodeAlfa\Css2Xpath\Selector\AbstractSelector
{
    final public function __construct(
        /** @var SplObjectStorage<CssSelector, null> */
        protected SplObjectStorage $selectors
    ) {
    }
    public function render(): string
    {
        $selectors = [];
        /** @var CssSelector $selector */
        foreach ($this->selectors as $selector) {
            $selectors[] = $selector->render();
        }
        return implode('|', $selectors);
    }
    public static function create(SelectorFactoryInterface $selectorFactory, string $css): static
    {
        /** @var SplObjectStorage<CssSelector, null> $selectors */
        $selectors = new SplObjectStorage();
        $selectorStrings = preg_split('#(?:[^,(\s]++|(?<fn>\((?>[^()]++|(?&fn))*+\))|\s++)*?\K(?:\s*+,\s*+|$)+#', $css, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($selectorStrings as $selectorString) {
            $selectors->attach($selectorFactory->createCssSelector($selectorFactory, $selectorString));
        }
        return new static($selectors);
    }
    /**
     * @return SplObjectStorage<CssSelector, null>
     */
    public function getSelectors(): SplObjectStorage
    {
        return $this->selectors;
    }
}
