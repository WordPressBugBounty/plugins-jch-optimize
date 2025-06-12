<?php

namespace CodeAlfa\Css2Xpath\Selector;

use CodeAlfa\Css2Xpath\SelectorFactoryInterface;
use CodeAlfa\RegexTokenizer\Css;
use SplObjectStorage;

use function preg_match;
use function preg_match_all;

use const PREG_SET_ORDER;

class CssSelector extends \CodeAlfa\Css2Xpath\Selector\AbstractSelector
{
    use Css;

    protected SelectorFactoryInterface $selectorFactory;
    protected ?\CodeAlfa\Css2Xpath\Selector\TypeSelector $type;
    protected ?\CodeAlfa\Css2Xpath\Selector\IdSelector $id;
    /**
     * @var SplObjectStorage<ClassSelector, null>
     */
    protected SplObjectStorage $classes;
    /**
     * @var SplObjectStorage<AttributeSelector, null>
     */
    protected SplObjectStorage $attributes;
    /**
     * @var SplObjectStorage<PseudoSelector, null>
     */
    protected SplObjectStorage $pseudoSelectors;
    protected string $combinator;
    protected \CodeAlfa\Css2Xpath\Selector\CssSelector|string|null $descendant;
    final public function __construct(SelectorFactoryInterface $selectorFactory, ?\CodeAlfa\Css2Xpath\Selector\TypeSelector $type = null, ?\CodeAlfa\Css2Xpath\Selector\IdSelector $id = null, ?SplObjectStorage $classes = null, ?SplObjectStorage $attributes = null, ?SplObjectStorage $pseudoSelectors = null, string $combinator = '', ?string $descendant = null)
    {
        $this->selectorFactory = $selectorFactory;
        $this->type = $type;
        $this->id = $id;
        $this->classes = $classes ?? new SplObjectStorage();
        $this->attributes = $attributes ?? new SplObjectStorage();
        $this->pseudoSelectors = $pseudoSelectors ?? new SplObjectStorage();
        $this->combinator = $combinator;
        $this->descendant = $descendant;
    }
    public static function create(SelectorFactoryInterface $selectorFactory, string $css): static
    {
        $type = null;
        $id = null;
        $classes = new SplObjectStorage();
        $attributes = new SplObjectStorage();
        $pseudoSelectors = new SplObjectStorage();
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
                static::addClassSelector($classes, $selectorFactory, $match);
            }
            if (!empty($match['attrName'])) {
                static::addAttributeSelector($attributes, $selectorFactory, $match);
            }
            if (!empty($match['pseudoSelector'])) {
                static::addPseudoSelector($pseudoSelectors, $selectorFactory, $match);
            }
            if (isset($match['combinator'])) {
                $combinator = $match['combinator'];
                $descendant = static::createDescendant($selectorFactory, $match);
            }
        }
        return new static($selectorFactory, $type, $id, $classes, $attributes, $pseudoSelectors, $combinator, $descendant);
    }
    protected static function createTypeSelector(SelectorFactoryInterface $selectorFactory, array $match): \CodeAlfa\Css2Xpath\Selector\TypeSelector
    {
        return $selectorFactory->createTypeSelector($match['type'], $match['typeSeparator'] ? $match['typeNs'] : null);
    }
    protected static function createIdSelector(SelectorFactoryInterface $selectorFactory, array $match): \CodeAlfa\Css2Xpath\Selector\IdSelector
    {
        return $selectorFactory->createIdSelector($match['id']);
    }
    protected static function addClassSelector(SplObjectStorage $classesStorage, SelectorFactoryInterface $selectorFactory, array $match): void
    {
        $classesStorage->attach($selectorFactory->createClassSelector($match['class']));
    }
    protected static function addAttributeSelector(SplObjectStorage $attributeStorage, SelectorFactoryInterface $selectorFactory, array $match): void
    {
        $attributeStorage->attach($selectorFactory->createAttributeSelector($match['attrName'], $match['attrValue'] ?? '', $match['attrOperator'] ?? '', $match['attrSeparator'] ? $match['attrNs'] : null));
    }
    protected static function addPseudoSelector(SplObjectStorage $pseudoSelectorsStorage, SelectorFactoryInterface $selectorFactory, array $match): void
    {
        if (preg_match("#is|not|where|has#", $match['pseudoSelector']) && !empty($match['pseudoSelectorList'])) {
            $pseudoSelectorList = $match['pseudoSelectorList'];
            $modifier = '';
        } else {
            $pseudoSelectorList = null;
            $modifier = !empty($match['pseudoSelectorList']) ? $match['pseudoSelectorList'] : '';
        }
        $pseudoSelectorsStorage->attach($selectorFactory->createPseudoSelector($selectorFactory, $match['pseudoSelector'], $match['pseudoPrefix'], $pseudoSelectorList, $modifier));
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
        return $this->renderTypeSelector() . $this->renderIdSelector() . $this->renderClassSelector() . $this->renderAttributeSelector() . $this->renderPseudoSelector() . $this->renderDescendant();
    }
    public function render(): string
    {
        $xpath = $this->internalRender();
        return "descendant-or-self::{$xpath}";
    }
    private function renderTypeSelector(): string
    {
        if (($type = $this->getType()) !== null) {
            return $type->render();
        }
        return "*";
    }
    private function renderIdSelector(): string
    {
        if (($id = $this->getid()) !== null) {
            return $id->render();
        }
        return '';
    }
    private function renderClassSelector(): string
    {
        $xpath = '';
        foreach ($this->getClasses() as $class) {
            $xpath .= $class->render();
        }
        return $xpath;
    }
    private function renderAttributeSelector(): string
    {
        $xpath = '';
        foreach ($this->getAttributes() as $attribute) {
            $xpath .= $attribute->render();
        }
        return $xpath;
    }
    private function renderPseudoSelector(): string
    {
        $pseudoXpath = '';
        foreach ($this->getPseudoSelectors() as $pseudoSelector) {
            $pseudoXpath .= $pseudoSelector->render();
        }
        return $pseudoXpath;
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
    /**
     * @return SplObjectStorage<ClassSelector, null>
     */
    public function getClasses(): SplObjectStorage
    {
        return $this->classes;
    }
    /**
     * @return SplObjectStorage<AttributeSelector, null>
     */
    public function getAttributes(): SplObjectStorage
    {
        return $this->attributes;
    }
    /**
     * @return SplObjectStorage<PseudoSelector, null>
     */
    public function getPseudoSelectors(): SplObjectStorage
    {
        return $this->pseudoSelectors;
    }
    public function getCombinator(): string
    {
        return $this->combinator;
    }
    public function getDescendant(): static|null
    {
        if (\is_string($this->descendant)) {
            $this->descendant = $this->selectorFactory->createCssSelector($this->selectorFactory, $this->descendant);
        }
        return $this->descendant;
    }
}
