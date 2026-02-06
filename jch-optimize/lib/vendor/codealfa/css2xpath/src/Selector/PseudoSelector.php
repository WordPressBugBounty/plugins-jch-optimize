<?php

namespace CodeAlfa\Css2Xpath\Selector;

use CodeAlfa\Css2Xpath\SelectorFactoryInterface;

use function preg_replace;

abstract class PseudoSelector extends \CodeAlfa\Css2Xpath\Selector\AbstractSelector
{
    protected string $prefix;
    public function __construct(protected SelectorFactoryInterface $selectorFactory, protected string $name, protected \CodeAlfa\Css2Xpath\Selector\CssSelectorList|string|null $selectorList = null, protected string $modifier = '', protected ?string $elementName = null)
    {
    }
    public function getName(): string
    {
        return $this->name;
    }
    /**
     * General-purpose CSS → XPath mapping for pseudo-classes.
     *
     * IMPORTANT (library semantics):
     *  - Unknown or unsupported pseudo-classes return an empty string (''),
     *    meaning "ignore this pseudo" and match as if it wasn’t present.
     */
    public function render(): string
    {
        return match ($this->getName()) {
            // --- Form control / state (HTML-ish approximations) ---
            'enabled' => "not(@disabled)",
            'disabled' => "@disabled",
            'read-only' => "@readonly or @disabled",
            'read-write' => "not(@readonly) and not(@disabled)",
            'checked' => "@selected or @checked",
            'required' => "@required",
            // --- Structural / document-related ---
            'root' => "not(parent::*)",
            'empty' => "not(*) and not(normalize-space())",
            'first-child' => "not(preceding-sibling::*)",
            'last-child' => "not(following-sibling::*)",
            'only-child' => "not(preceding-sibling::*) and not(following-sibling::*)",
            'first-of-type' => "not(preceding-sibling::{$this->getElementName()})",
            'last-of-type' => "not(following-sibling::{$this->getElementName()})",
            'only-of-type' => "not(preceding-sibling::{$this->getElementName()})" . " and not(following-sibling::{$this->getElementName()})",
            // --- Nth-* pseudo-classes ---
            'nth-child' => $this->renderNthChildPredicate(),
            'nth-last-child' => $this->renderNthLastChildPredicate(),
            'nth-of-type' => $this->renderNthOfTypePredicate(),
            'nth-last-of-type' => $this->renderNthLastOfTypePredicate(),
            // --- Functional pseudo-classes using selector lists ---
            'not' => "not({$this->renderNotSelectorList()})",
            'has' => "count({$this->renderHasSelectorList()}) > 0",
            // :is() / :where() – “matches any of these selectors”
            'is', 'where' => "{$this->renderIsSelectorList()}",
            // Unknown / unsupported pseudos are ignored
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
    protected function renderNotSelectorList(): string
    {
        return $this->renderSelectorList('self');
    }
    protected function renderHasSelectorList(): string
    {
        return $this->renderSelectorList();
    }
    protected function renderIsSelectorList(): string
    {
        return $this->renderSelectorList('self');
    }
    protected function renderSelectorList(?string $axis = null): string
    {
        return (string) $this->getSelectorList()?->render($axis);
    }
    public function getModifier(): string
    {
        return $this->modifier;
    }
    // -------------------------------------------------------------------------
    // Nth-* helpers
    // -------------------------------------------------------------------------
    /**
     * Parse an "an+b" nth formula into [a, b].
     *
     * Supports:
     *  - "odd", "even"
     *  - "n", "2n", "-n", "+3n-1", "2n+1", etc.
     *  - pure numbers: "3", "-2"
     *
     * Returns null if the formula is not understood.
     */
    protected function parseNthFormula(string $formula): ?array
    {
        $formula = strtolower(trim($formula));
        $formula = preg_replace('/\s+/', '', $formula);
        if ($formula === 'odd') {
            return [2, 1];
        }
        if ($formula === 'even') {
            return [2, 0];
        }
        // an+b
        if (preg_match('/^([+-]?\d*)n([+-]\d+)?$/', $formula, $m)) {
            $aStr = $m[1];
            $bStr = $m[2] ?? '';
            if ($aStr === '' || $aStr === '+') {
                $a = 1;
            } elseif ($aStr === '-') {
                $a = -1;
            } else {
                $a = (int) $aStr;
            }
            $b = $bStr !== '' ? (int) $bStr : 0;
            return [$a, $b];
        }
        // Just a number b
        if (preg_match('/^[+-]?\d+$/', $formula)) {
            return [0, (int) $formula];
        }
        // Unknown
        return null;
    }
    /**
     * Build a predicate for an nth formula applied to some "position" expression.
     *
     * $positionExpr is an XPath expression that yields the index to which the
     * an + b formula applies (e.g. "position()", "last() - position() + 1",
     * "count(preceding-sibling::...)+1", etc.).
     */
    protected function buildNthPredicateForPosition(string $positionExpr, int $a, int $b): string
    {
        // a = 0 → only the b-th element (if b >= 1), else never matches.
        if ($a === 0) {
            if ($b <= 0) {
                return "false()";
            }
            return "{$positionExpr} = {$b}";
        }
        $absA = abs($a);
        if ($a > 0) {
            // a > 0: pos >= b and (pos - b) mod a == 0
            return "({$positionExpr} >= {$b}) and (({$positionExpr} - {$b}) mod {$a} = 0)";
        }
        // a < 0: pos <= b and (b - pos) mod |a| == 0
        return "({$positionExpr} <= {$b}) and (({$b} - {$positionExpr}) mod {$absA} = 0)";
    }
    protected function renderNthChildPredicate(): string
    {
        $parsed = $this->parseNthFormula($this->getModifier());
        if ($parsed === null) {
            // Unknown formula → ignore in general-purpose library
            return '';
        }
        [$a, $b] = $parsed;
        $pos = "count(preceding-sibling::*) + 1";
        return $this->buildNthPredicateForPosition($pos, $a, $b);
    }
    protected function renderNthLastChildPredicate(): string
    {
        $parsed = $this->parseNthFormula($this->getModifier());
        if ($parsed === null) {
            return '';
        }
        [$a, $b] = $parsed;
        // Index counted from the end: 1 for last(), 2 for second-last, etc.
        $pos = "count(following-sibling::*) + 1";
        return $this->buildNthPredicateForPosition($pos, $a, $b);
    }
    protected function renderNthOfTypePredicate(): string
    {
        $parsed = $this->parseNthFormula($this->getModifier());
        if ($parsed === null) {
            return '';
        }
        [$a, $b] = $parsed;
        // Index among siblings of the same name()
        $pos = "count(preceding-sibling::{$this->getElementName()}) + 1";
        return $this->buildNthPredicateForPosition($pos, $a, $b);
    }
    protected function renderNthLastOfTypePredicate(): string
    {
        $parsed = $this->parseNthFormula($this->getModifier());
        if ($parsed === null) {
            return '';
        }
        [$a, $b] = $parsed;
        // Index from the end among siblings of the same name()
        $pos = "count(following-sibling::{$this->getElementName()}) + 1";
        return $this->buildNthPredicateForPosition($pos, $a, $b);
    }
    protected function getElementName(): string
    {
        return $this->elementName ?? '*';
    }
}
