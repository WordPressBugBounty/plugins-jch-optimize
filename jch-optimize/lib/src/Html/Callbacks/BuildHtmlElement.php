<?php

namespace JchOptimize\Core\Html\Callbacks;

use JchOptimize\Core\Exception\PregErrorException;
use JchOptimize\Core\Html\CallbackInterface;
use JchOptimize\Core\Html\ElementObject;
use JchOptimize\Core\Html\Elements\BaseElement;
use JchOptimize\Core\Html\HtmlElementBuilder;
use JchOptimize\Core\Html\HtmlElementInterface;
use JchOptimize\Core\Html\Parser;

class BuildHtmlElement implements CallbackInterface
{
    protected string $regex = '';
    protected BaseElement $element;

    /**
     * @param string[] $matches [ 0 => 'full match'
     *                          1 => 'element name'
     *                          2 => 'attributes'
     *                          3 => 'content' ]
     *
     * @throws PregErrorException
     */
    public function processMatches(array $matches): string
    {
        if (empty($matches[0])) {
            return $matches[0];
        }
        $name = $matches[1];
        $this->element = HtmlElementBuilder::$name();
        $attributesRegex = Parser::htmlAttributeWithCaptureValueToken('', \true, \true);
        \preg_replace_callback('#'.$attributesRegex.'#i', [$this, 'loadElementAttributes'], $matches[2]);
        if (isset($matches[3])) {
            $this->loadChildren($matches[3]);
        } else {
            $this->element->setOmitClosingTag(\true);
        }

        return $matches[0];
    }

    public function getElement(): HtmlElementInterface
    {
        return $this->element;
    }

    public function loadElementAttributes(array $matches): string
    {
        $parts = \preg_split('#\\s*=\\s*#', $matches[0]);
        $value = $matches[2] ?? '';
        $delimiter = $matches[1] ?? '"';
        $this->element->attribute($parts[0], $value, $delimiter);

        return '';
    }

    /**
     * @throws PregErrorException
     */
    private function loadChildren(string $content): void
    {
        $parser = new Parser();
        $parser->addExclude(Parser::htmlCommentToken());
        $parser->alsoExcludeStringsAndComments = \true;
        $voidElementObj = new ElementObject();
        $voidElementObj->setNamesArray(HtmlElementBuilder::$voidElements);
        $voidElementObj->bSelfClosing = \true;
        $parser->addElementObject($voidElementObj);
        $elementObj = new ElementObject();
        $parser->addElementObject($elementObj);
        $parser->setOnlyMatchElements(\false);

        /** @var array<string[]> $matches */
        $matches = $parser->findMatches($content, \PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!empty($match[1])) {
                $this->element->addChild($match[1]);
            }
            if (!empty($match[2])) {
                $child = HtmlElementBuilder::load($match[2]);
                $child->setParent($this->element->getElementName());
                $this->element->addChild($child);
            }
        }
    }
}
