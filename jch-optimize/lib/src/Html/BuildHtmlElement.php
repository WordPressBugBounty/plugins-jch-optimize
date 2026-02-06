<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Html;

use JchOptimize\Core\Exception\PregErrorException;
use JchOptimize\Core\Exception\RuntimeException;
use JchOptimize\Core\Html\Elements\BaseElement;
use LogicException;

use function preg_match;
use function preg_match_all;

use const PREG_SET_ORDER;

class BuildHtmlElement
{
    protected string $regex = '';

    protected ?BaseElement $element = null;

    /**
     * @throws PregErrorException
     */
    public function build(string $html): void
    {
        $elementRegex = self::htmlElementWithCaptureValueToken();
        $result = preg_match("#^{$elementRegex}$#s", $html, $matches);

        if ($result === false) {
            throw new RuntimeException('Failed to parse HTML string');
        }

        $this->buildFromMatch($matches);
    }

    /**
     * @throws PregErrorException
     */
    public function buildFromMatch(array $matches): void
    {
        if (!isset($matches['name'])) {
            $this->build($matches[0]);

            return;
        }

        $name = strtolower($matches['name']);
        $this->element = HtmlElementBuilder::$name();

        $attrsText = $matches['attributes'] ?? '';
        if ($attrsText !== '') {
            $this->loadAttributesFromText($attrsText);
        }

        if (!empty($matches['content'])) {
            $this->loadChildren($matches['content']);
        }

        if (empty($matches['endTag'])) {
            $this->element->setOmitClosingTag(true);
        }
    }

    private function loadAttributesFromText(string $attributesText): void
    {
        $attributesRegex = self::htmlAttributeWithCaptureValueToken();

        preg_match_all(
            '#' . $attributesRegex . '#ix',
            $attributesText,
            $attributes,
            PREG_SET_ORDER
        );

        foreach ($attributes as $attribute) {
            $name = $attribute['name'];
            $value = $attribute['value'] ?? '';
            $delimiter = $attribute['delimiter'] ?? '"';

            $this->element?->attribute($name, $value, $delimiter);
        }
    }


    public function getElement(): BaseElement
    {
        if ($this->element === null) {
            throw new LogicException('Element not set');
        }

        return $this->element;
    }

    private static function htmlElementWithCaptureValueToken(): string
    {
        $name = Parser::htmlGenericElementNameToken();
        $attributes = Parser::htmlAttributesListToken();
        $endTag = Parser::htmlEndTagToken(Parser::htmlGenericElementNameToken());

        return "<(?<name>{$name})\b(?:\s++(?<attributes>{$attributes}+))?/?>(?:(?<content>.*)(?<endTag>{$endTag}))?";
    }

    private static function htmlAttributeWithCaptureValueToken(): string
    {
        return "(?<name>[^\s/\"'=<>]++)(?:\s*+=\s*+(?<delimiter>['\"]?)(?|"
            . "(?<=[\"])(?<value>(?>[^\"\\\\]++|\\\\.)*+)\""
            . "|(?<=['])(?<value>(?>[^'\\\\]++|\\\\.)*+)'"
            . "|(?<=[=])(?<value>[^\s>]++)"
            . "))?";
    }

    /**
     * @throws PregErrorException
     */
    private function loadChildren(string $content): void
    {
        if ($content === '') {
            return;
        }

        $voidElement = Parser::htmlVoidElementToken();
        $textElement = Parser::htmlElementToken();
        //Have to use a different variable to avoid duplicating capturing group names
        $textElementMatch = Parser::htmlElementToken();
        $dqStr = Parser::doubleQuoteStringToken();
        $sqStr = Parser::singleQuoteStringToken();
        $btStr = Parser::backTickStringToken();
        $bc = Parser::blockCommentToken();
        $lc = Parser::lineCommentToken();
        //Regular expression literal
        $rx = '/(?![/*])(?>(?(?=\\\\)\\\\.|\[(?>(?:\\\\.)?[^\]\r\n]*+)+?\])?[^\\\\/\r\n\[]*+)+?/';

        $htmlElementRegex = "(?:{$voidElement}|{$textElement})";
        $regex = "(?<string>(?>[^<'\"/`]++|{$bc}|{$lc}|{$rx}|{$dqStr}|{$sqStr}|{$btStr}|/|(?!{$htmlElementRegex})<)++)"
            . "|(?<element>(?:{$voidElement}|{$textElementMatch}))";

        preg_match_all(
            "#{$regex}#six",
            $content,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            if (isset($match['element'])) {
                $child = HtmlElementBuilder::load($match['element']);
                $child->setParent($this->getElement()->getElementName());
                $this->getElement()->addChild($child);
            } elseif (isset($match['string'])) {
                $this->getElement()->addChild($match['string']);
            }
        }
    }
}
