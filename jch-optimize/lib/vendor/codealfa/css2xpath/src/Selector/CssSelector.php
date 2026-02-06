<?php

namespace CodeAlfa\Css2Xpath\Selector;

use CodeAlfa\Css2Xpath\Collections\AttributeCollection;
use CodeAlfa\Css2Xpath\Collections\ClassCollection;
use CodeAlfa\Css2Xpath\Collections\PseudoClassCollection;
use CodeAlfa\Css2Xpath\SelectorFactoryInterface;
use CodeAlfa\RegexTokenizer\Css;

use function preg_match;
use function preg_match_all;

use const PREG_SET_ORDER;

class CssSelector extends \CodeAlfa\Css2Xpath\Selector\AbstractSelector
{
    use Css;

    final public function __construct(protected SelectorFactoryInterface $selectorFactory, protected ?\CodeAlfa\Css2Xpath\Selector\TypeSelector $type = null, protected ?\CodeAlfa\Css2Xpath\Selector\IdSelector $id = null, protected ?ClassCollection $classes = null, protected ?AttributeCollection $attributes = null, protected ?PseudoClassCollection $pseudoClasses = null, protected ?\CodeAlfa\Css2Xpath\Selector\PseudoElementSelector $pseudoElement = null, protected string $combinator = '', protected \CodeAlfa\Css2Xpath\Selector\CssSelector|string|null $descendant = null)
    {
    }
    public static function create(SelectorFactoryInterface $selectorFactory, string $css): static
    {
        $type = null;
        $id = null;
        $classCollection = new ClassCollection();
        $attributeCollection = new AttributeCollection();
        $pseudoClassCollection = new PseudoClassCollection();
        $pseudoElement = null;
        $combinator = '';
        $descendant = null;
        $elRx = self::cssTypeSelectorWithCaptureValueToken();
        $idRx = self::cssIdSelectorWithCaptureValueToken();
        $classRx = self::cssClassSelectorWithCaptureValueToken();
        $attrRx = self::cssAttributeSelectorWithCaptureValueToken();
        $pseudoRx = self::cssPseudoSelectorWithCaptureValueToken();
        $descRx = self::cssDescendantSelectorWithCaptureValueToken();
        $bc = self::blockCommentToken();
        $regex = "(?:{$elRx})?(?:{$idRx})?(?:{$classRx})?(?:{$attrRx})?(?:{$pseudoRx})?(?:{$descRx})?(?:\\s*+{$bc})?";
        preg_match_all("#{$regex}#", $css, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!empty($match['type'])) {
                $type = static::createTypeSelector($selectorFactory, $match);
            }
            if (!empty($match['id'])) {
                $id = static::createIdSelector($selectorFactory, $match);
            }
            if (!empty($match['class'])) {
                static::addClassSelector($classCollection, $selectorFactory, $match);
            }
            if (!empty($match['attrName'])) {
                static::addAttributeSelector($attributeCollection, $selectorFactory, $match);
            }
            if (!empty($match['pseudoSelector'])) {
                static::addPseudoSelector($pseudoClassCollection, $pseudoElement, $type, $selectorFactory, $match);
            }
            if (isset($match['combinator'])) {
                $combinator = $match['combinator'];
                $descendant = static::createDescendant($selectorFactory, $match);
            }
        }
        return new static($selectorFactory, $type, $id, $classCollection, $attributeCollection, $pseudoClassCollection, $pseudoElement, $combinator, $descendant);
    }
    protected static function createTypeSelector(SelectorFactoryInterface $selectorFactory, array $match): \CodeAlfa\Css2Xpath\Selector\TypeSelector
    {
        return $selectorFactory->createTypeSelector($match['type'], $match['typeSeparator'] ? $match['typeNs'] : null);
    }
    protected static function createIdSelector(SelectorFactoryInterface $selectorFactory, array $match): \CodeAlfa\Css2Xpath\Selector\IdSelector
    {
        return $selectorFactory->createIdSelector($match['id']);
    }
    protected static function addClassSelector(ClassCollection $classCollection, SelectorFactoryInterface $selectorFactory, array $match): void
    {
        $classCollection->offsetSet($selectorFactory->createClassSelector($match['class']));
    }
    protected static function addAttributeSelector(AttributeCollection $attributeStorage, SelectorFactoryInterface $selectorFactory, array $match): void
    {
        $attributeStorage->offsetSet($selectorFactory->createAttributeSelector($match['attrName'], $match['attrValue'] ?? '', $match['attrOperator'] ?? '', $match['attrSeparator'] ? $match['attrNs'] : null));
    }
    protected static function addPseudoSelector(PseudoClassCollection $pseudoClassCollection, ?\CodeAlfa\Css2Xpath\Selector\PseudoElementSelector &$pseudoElementSelector, ?\CodeAlfa\Css2Xpath\Selector\TypeSelector $type, SelectorFactoryInterface $selectorFactory, array $match): void
    {
        $prefix = $match['pseudoPrefix'];
        $selector = $match['pseudoSelector'];
        $selectorList = $match['pseudoSelectorList'] ?? '';
        if ($prefix === '::' || in_array($selector, ['before', 'after', 'first-line', 'first-letter'])) {
            $pseudoElementSelector = $selectorFactory->createPseudoElementSelector($selectorFactory, $selector);
        } else {
            if (preg_match("#is|not|where|has#", $selector) && !empty($selectorList)) {
                $pseudoSelectorList = $selectorList;
                $modifier = '';
            } else {
                $pseudoSelectorList = null;
                $modifier = !empty($selectorList) ? $selectorList : '';
            }
            $pseudoClassCollection->offsetSet($selectorFactory->createPseudoClassSelector($selectorFactory, $selector, $pseudoSelectorList, $modifier, $type?->getName()));
        }
    }
    protected static function createDescendant(SelectorFactoryInterface $selectorFactory, array $match): string
    {
        return $match['descendant'];
    }
    private static function cssTypeSelectorWithCaptureValueToken(): string
    {
        return "^(?:(?<typeNs>[a-zA-Z0-9-]*+)(?<typeSeparator>\\|))?(?<type>(?:[*&a-zA-Z0-9-]++))";
    }
    private static function cssIdSelectorWithCaptureValueToken(): string
    {
        $e = self::cssEscapedString();
        return "\\#(?<id>(?>[a-zA-Z0-9_-]++|{$e})++)";
    }
    private static function cssClassSelectorWithCaptureValueToken(): string
    {
        $e = self::cssEscapedString();
        return "\\.(?<class>(?>[a-zA-Z0-9_-]++|{$e})++)";
    }
    private static function cssAttributeSelectorWithCaptureValueToken(): string
    {
        $e = self::cssEscapedString();
        return "\\[(?:(?<attrNs>[a-zA-Z0-9-]*+)(?<attrSeparator>\\|))?(?<attrName>(?>[a-zA-Z0-9_-]++|{$e})++)" . "(?:\\s*+(?<attrOperator>[~|\$*^]?=)\\s*?" . "(?|\"(?<attrValue>(?>[^\\\\\"\\]]++|{$e})*+)\"" . "|'(?<attrValue>(?>[^\\\\'\\]]++|{$e})*+)'" . "|(?<attrValue>(?>[^\\\\\\]]++|{$e})*+)))?(?:\\s++(?<attrModifier>[iIsS]))?\\s*+\\]";
    }
    private static function cssPseudoSelectorWithCaptureValueToken(): string
    {
        return "(?<pseudoPrefix>::?)" . "(?<pseudoSelector>[a-zA-Z0-9-]++)(?<fn>\\((?<pseudoSelectorList>(?>[^()]++|(?&fn))*+)\\))?";
    }
    private static function cssDescendantSelectorWithCaptureValueToken(): string
    {
        return "\\s*?(?<combinator>[ >+~|])\\s*+(?<descendant>[^ >+~|].*+)";
    }
    private function internalRender(): string
    {
        $node = $this->renderTypeSelector();
        $filters = [];
        $filters = $this->renderIdSelector($filters);
        $filters = $this->renderClassSelector($filters);
        $filters = $this->renderAttributeSelector($filters);
        $filters = $this->renderPseudoClassSelector($filters);
        $predicate = $this->renderPredicateFromFilters($filters);
        return "{$node}{$predicate}{$this->renderDescendant()}";
    }
    private function renderPredicateFromFilters(array $filters): string
    {
        if (count($filters) > 1) {
            $filters = array_map(fn($f) => preg_match('#\bor\b|[=<>]#i', $f) ? "({$f})" : $f, $filters);
        }
        return !empty($filters) ? '[' . implode(' and ', $filters) . ']' : '';
    }
    public function render(?string $axis = null): string
    {
        $xpath = $this->internalRender();
        $axis = $axis ?? 'descendant-or-self';
        return "{$axis}::{$xpath}";
    }
    private function renderTypeSelector(): string
    {
        return ($type = $this->getType()) !== null ? $type->render() : '*';
    }
    private function renderIdSelector(array $filters): array
    {
        if (($id = $this->getid()) !== null) {
            $filters[] = $id->render();
        }
        return $filters;
    }
    private function renderClassSelector(array $filters): array
    {
        foreach ($this->getClasses() as $class) {
            $filters[] = $class->render();
        }
        return $filters;
    }
    private function renderAttributeSelector(array $filters): array
    {
        foreach ($this->getAttributes() as $attribute) {
            $filters[] = $attribute->render();
        }
        return $filters;
    }
    private function renderPseudoClassSelector(array $filters): array
    {
        foreach ($this->getPseudoClasses() as $pseudoClass) {
            if ($pseudoSelector = $pseudoClass->render()) {
                $filters[] = $pseudoSelector;
            }
        }
        return $filters;
    }
    private function renderDescendant(): string
    {
        if (($descendant = $this->getDescendant()) instanceof \CodeAlfa\Css2Xpath\Selector\CssSelector) {
            $axes = match ($this->getCombinator()) {
                '>' => 'child::',
                '+' => 'following-sibling::*[1]/self::',
                '~' => 'following-sibling::',
                ' ' => 'descendant::',
                default => 'descendant-or-self::',
            };
            return "/{$axes}{$descendant->internalRender()}";
        }
        return '';
    }
    public function getType(): ?\CodeAlfa\Css2Xpath\Selector\TypeSelector
    {
        return $this->type;
    }
    public function getId(): ?\CodeAlfa\Css2Xpath\Selector\IdSelector
    {
        return $this->id;
    }
    public function getClasses(): ClassCollection
    {
        return $this->classes;
    }
    public function getAttributes(): AttributeCollection
    {
        return $this->attributes;
    }
    public function getPseudoClasses(): PseudoClassCollection
    {
        return $this->pseudoClasses;
    }
    public function getPseudoElement(): ?\CodeAlfa\Css2Xpath\Selector\PseudoElementSelector
    {
        return $this->pseudoElement;
    }
    public function getCombinator(): string
    {
        return $this->combinator;
    }
    public function getDescendant(): static|null
    {
        if (is_string($this->descendant)) {
            $this->descendant = $this->selectorFactory->createCssSelector($this->selectorFactory, $this->descendant);
        }
        return $this->descendant;
    }
}
