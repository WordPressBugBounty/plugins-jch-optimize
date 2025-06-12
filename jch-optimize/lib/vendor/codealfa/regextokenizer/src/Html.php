<?php

/**
 * @package   codealfa/regextokenizer
 * @author    Samuel Marshall <sdmarshall73@gmail.com>
 * @copyright Copyright (c) 2020 Samuel Marshall
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace CodeAlfa\RegexTokenizer;

trait Html
{
    use \CodeAlfa\RegexTokenizer\Base;

    private static string $cgName = 'cgName';
    private static int $cgIndex = 0;
    //language=RegExp
    public static function htmlCommentToken(): string
    {
        return '<!--(?>[^>!]++|[!>]++)*?(?<=--)!?>';
    }
    //language=RegExp
    public static function htmlElementsToken(array $elements, bool $voidElements = \false): string
    {
        $result = [];
        foreach ($elements as $element) {
            $result[] = $voidElements ? self::htmlVoidElementToken($element) : self::htmlElementToken($element);
        }
        return '(?:' . \implode('|', $result) . ')';
    }
    //language=RegExp
    public static function htmlElementToken(string $name = null): string
    {
        $startTag = self::htmlStartTagToken($name);
        $textContent = self::htmlTextContentToken();
        $endTag = self::htmlEndTagToken();
        return "{$startTag}{$textContent}?{$endTag}";
    }
    public static function htmlVoidElementToken(string $name = null): string
    {
        $element = $name ?? '(?:area|base|br|col|command|embed|hr|img|input|keygen|link|meta|param|source|track|wbr)';
        $attributes = self::htmlAttributesListToken();
        return "<{$element}\\b(\\s++{$attributes}+)?/?>";
    }
    //language=RegExp
    public static function htmlNestedElementToken(string $name): string
    {
        $startTag = self::htmlStartTagToken($name);
        $endTag = self::htmlEndTagToken($name);
        return "(?<{$name}>{$startTag}(?>(?:[^<]++|(?!</?{$name})<)++|(?&{$name}))*+{$endTag})";
    }
    //language=RegExp
    public static function htmlGenericElementNameToken(): string
    {
        return '[a-zA-Z0-9-]++';
    }
    /**
     * Regex for parsing an HTML attribute
     *
     * @return string
     * @deprecated Will be removed in 3.0
     */
    //language=RegExp
    protected static function parseAttributesStatic(): string
    {
        return '(?>' . self::htmlAttributeWithCaptureValueToken() . '\\s*+)*?';
    }
    /**
     * Regex token for an HTML attribute, optionally capturing the value in a capture group
     *
     * @param string $attrName
     * @param bool $captureValue
     * @param bool $captureDelimiter
     * @param string $matchedValue
     *
     * @return string
     * @deprecated Will be removed in 3.0
     */
    //language=RegExp
    public static function htmlAttributeWithCaptureValueToken(string $attrName = '', bool $captureValue = \false, bool $captureDelimiter = \false, string $matchedValue = ''): string
    {
        $name = $attrName != '' ? $attrName : '[^\\s/"\'=>]++';
        $delimiter = $captureDelimiter ? '([\'"]?)' : '[\'"]?';
        //If we don't need to match a value then the value of attribute is optional
        if ($matchedValue == '') {
            $attribute = $name . '(?:\\s*+=\\s*+(?>' . $delimiter . ')<<' . self::htmlAttributeValueToken() . '>>[\'"]?)?';
        } else {
            $attribute = $name . '\\s*+=\\s*+(?>' . $delimiter . ')' . $matchedValue . '<<' . self::htmlAttributeValueToken() . '>>[\'"]?';
        }
        return self::prepare($attribute, $captureValue);
    }
    /**
     * Regex token for an HTML attribute value
     *
     * @return string
     * @deprecated Will be removed in 3.0
     */
    //language=RegExp
    public static function htmlAttributeValueToken(): string
    {
        return '(?:' . self::stringValueToken() . '|' . self::htmlUnquotedAttributeValueToken() . ')';
    }
    /**
     * Regex token for an unquoted HTML attribute value
     *
     * @return string
     * @deprecated Will be removed in 3.0
     */
    //language=RegExp
    public static function htmlUnquotedAttributeValueToken(): string
    {
        return '(?<==)[^\\s*+>]++';
    }
    /**
     * Regex token for a self closing HTML element
     *
     * @param string $element Name of element
     *
     * @return string
     * @deprecated Will be removed in 3.0
     */
    public static function htmlSelfClosingElementToken(string $element = ''): string
    {
        return self::htmlElementToken($element);
    }
    public static function htmlAttributeToken(): string
    {
        $ds = self::doubleQuoteStringToken();
        $ss = self::singleQuoteStringToken();
        $bs = self::backTickStringToken();
        return "[^\\s/\"'=>`]++(?:\\s*+=\\s*+(?>{$ds}|{$ss}|{$bs}|(?<==)[^\\s>'\"`]++))?";
    }
    public static function htmlAttributesListToken(): string
    {
        $a = self::htmlAttributeToken();
        return "(?>{$a}|\\s++)*";
    }
    public static function htmlStartTagToken(string $name = null): string
    {
        $element = $name ?? self::htmlGenericElementNameToken();
        $attributes = self::htmlAttributesListToken();
        $gName = self::$cgName . ++self::$cgIndex;
        return "<(?<{$gName}>{$element})\\b(\\s++{$attributes}+)?>";
    }
    public static function htmlEndTagToken(string $name = null): string
    {
        $gName = $name ?? '(?&' . self::$cgName . self::$cgIndex . ')';
        return "</{$gName}\\s*+>";
    }
    public static function htmlTextContentToken(string $name = null): string
    {
        $et = self::htmlEndTagToken($name);
        return "(?>[^<]++|(?!{$et}})<)*";
    }
    public static function htmlStringToken(array $excludes = []): string
    {
        $c = self::htmlCommentToken();
        $e = self::htmlGenericStartTagToken();
        $ex = '';
        if ($excludes !== []) {
            $excludedElements = self::htmlElementsToken($excludes);
            $ex = "|{$excludedElements}";
        }
        return "(?>[^<]++|{$c}{$ex}|{$e}|<)*";
    }
    public static function htmlGenericStartTagToken(): string
    {
        $name = self::htmlGenericElementNameToken();
        $attributes = self::htmlAttributesListToken();
        return "<{$name}\\b(\\s++{$attributes}+)?/?>";
    }
}
