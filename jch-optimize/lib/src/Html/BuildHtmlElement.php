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
use JchOptimize\Core\Html\Elements\BaseElement;

use function preg_match;
use function preg_match_all;

use const PREG_SET_ORDER;

class BuildHtmlElement
{
    protected string $regex = '';

    protected BaseElement $element;

    /**
     * @throws PregErrorException
     */
    public function build(string $html): void
    {
        $elementRegex = self::htmlElementWithCaptureValueToken();
        preg_match("#^{$elementRegex}$#s", $html, $matches);

        $name = strtolower($matches['name']);
        $this->element = HtmlElementBuilder::$name();

        $attributesRegex = self::htmlAttributeWithCaptureValueToken();
        preg_match_all(
            '#' . $attributesRegex . '#ix',
            $matches['attributes'] ?? '',
            $attributes,
            PREG_SET_ORDER
        );

        foreach ($attributes as $attribute) {
            $name = $attribute['name'];
            $value = $attribute['value'] ?? '';
            $delimiter = $attribute['delimiter'] ?? '"';

            $this->element->attribute($name, $value, $delimiter);
        }

        if (isset($matches['content'])) {
            $this->loadChildren($matches['content']);
        } else {
            $this->element->setOmitClosingTag(true);
        }
    }

    public function getElement(): HtmlElementInterface
    {
        return $this->element;
    }

    private static function htmlElementWithCaptureValueToken(): string
    {
        $name = Parser::htmlGenericElementNameToken();
        $attributes = Parser::htmlAttributesListToken();
        $endTag = Parser::htmlEndTagToken(Parser::htmlGenericElementNameToken());

        return "<(?<name>{$name})\b(\s++(?<attributes>{$attributes}+))?/?>(?:(?<content>.*){$endTag})?";
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
        $rx =  '/(?![/*])(?>(?(?=\\\\)\\\\.|\[(?>(?:\\\\.)?[^\]\r\n]*+)+?\])?[^\\\\/\r\n\[]*+)+?/';

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
            if (!empty($match['element'])) {
                $child = HtmlElementBuilder::load($match['element']);
                $child->setParent($this->element->getElementName());
                $this->element->addChild($child);
            } elseif (!empty($match['string'])) {
                $this->element->addChild($match['string']);
            }
        }
    }
}
