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

trait Css
{
    use \CodeAlfa\RegexTokenizer\Base;

    public static function cssEscapedString(): string
    {
        return "\\\\[0-9a-fA-F]++\\s?|\\\\[^0-9a-fA-F\r\n]";
    }
    //language=RegExp
    public static function cssIdentToken(): string
    {
        $esc = self::cssEscapedString();
        return "(?>{$esc}|[a-zA-Z0-9_-]++)++";
    }
    /**
     * Regex token for a CSS url, optionally capturing the value in a capture group
     *
     * @param   bool  $shouldCaptureValue Whether to capture the value in a capture group
     *
     * @return string
     * @deprecated Will be removed in 3.0
     */
    //language=RegExp
    public static function cssUrlWithCaptureValueToken(bool $shouldCaptureValue = \false): string
    {
        $cssUrl = '(?:url\\(|(?<=url)\\()(?:\\s*+[\'"])?<<' . self::cssUrlValueToken() . '>>(?:[\'"]\\s*+)?\\)';
        return self::prepare($cssUrl, $shouldCaptureValue);
    }
    /**
     * Regex token for a CSS url value
     *
     * @return string
     * @deprecated Will be removed in 3.0
     */
    //language=RegExp
    public static function cssUrlValueToken(): string
    {
        return '(?:' . self::stringValueToken() . '|' . self::cssUnquotedUrlValueToken() . ')';
    }
    /**
     * Regex token for an unquoted CSS url value
     *
     * @return string
     * @deprecated Will be removed in 3.0
     */
    //language=RegExp
    public static function cssUnquotedUrlValueToken(): string
    {
        return '(?<=url\\()(?>\\s*+(?:\\\\.)?[^\\\\()\\s\'"]*+)++';
    }
    public static function cssUrlToken(): string
    {
        $esc = self::cssEscapedString();
        $dqStr = self::doubleQuoteStringToken();
        $sqStr = self::singleQuoteStringToken();
        return "url\\(\\s*+(?>{$dqStr}|{$sqStr}|(?:{$esc}|[^)\\\\]++)++)\\s*+\\)";
    }
    public static function cssSelectorListToken(): string
    {
        $bc = self::blockCommentToken();
        $esc = self::cssEscapedString();
        $dqStr = self::doubleQuoteStringToken();
        $sqStr = self::singleQuoteStringToken();
        return "(?<=^|[{}/\\s;])[^{}@/\\\\'\"\\s;]++(?>[^{}@/\\\\'\";]++|{$esc}|{$bc}|{$sqStr}|{$dqStr})*+(?={)";
    }
    public static function cssDeclarationToken(): string
    {
        $ident = self::cssIdentToken();
        $dqStr = self::doubleQuoteStringToken();
        $sqStr = self::singleQuoteStringToken();
        $bc = self::blockCommentToken();
        $esc = self::cssEscapedString();
        $brc = '(?<brc>\\((?>[^()]++|(?&brc))*+\\))';
        return "(?>{$ident}|\\s++|{$bc})*?:" . "(?>[^/()'\";{}@\\\\]++|{$brc}|{$dqStr}|{$sqStr}|{$bc}|{$esc}|[/])*?(?:;|\$|(?=}))";
    }
    public static function cssDeclarationListToken(): string
    {
        $bc = self::blockCommentToken();
        $dqStr = self::doubleQuoteStringToken();
        $sqStr = self::singleQuoteStringToken();
        $esc = self::cssEscapedString();
        $url = self::cssUrlToken();
        $nestingAtRule = self::cssNestingAtRulesToken();
        $nestingRule = self::cssBlockToken();
        return "(?<={)(?>(?>[^{}@/\\\\'\"u]++|{$bc}|{$dqStr}|{$sqStr}|{$esc}|{$url}|[/\\\\u]++|(?<={)(?=}))++" . "|{$nestingAtRule}|{$nestingRule})++(?=})";
    }
    public static function cssRuleToken(): string
    {
        $selectors = self::cssSelectorListToken();
        $cssBlock = self::cssBlockToken();
        return "{$selectors}{$cssBlock}";
    }
    public static function cssRuleListToken(): string
    {
        $cssRule = self::cssRuleToken();
        $bc = self::blockCommentToken();
        return "(?>\\s++|{$bc}|{$cssRule})++";
    }
    public static function cssRegularAtRulesToken(?string $name = null): string
    {
        $esc = self::cssEscapedString();
        $dqStr = self::doubleQuoteStringToken();
        $sqStr = self::singleQuoteStringToken();
        $bc = self::blockCommentToken();
        $url = self::cssUrlToken();
        $name = $name ?? '[a-zA-Z-]++';
        return "@{$name}\\s*+(?>[^{}@/\\\\'\"u;]++|{$esc}|{$bc}|{$dqStr}|{$sqStr}|{$url}|[/u])++;";
    }
    public static function cssBlockToken(): string
    {
        $bc = self::blockCommentToken();
        $esc = self::cssEscapedString();
        $dqStr = self::doubleQuoteStringToken();
        $sqStr = self::singleQuoteStringToken();
        static $cnt = 0;
        $cssBlock = 'cssBlock' . $cnt++;
        return "(?P<{$cssBlock}>{(?>(?:[^{}/\\\\'\"]++|{$bc}|{$esc}|{$dqStr}|{$sqStr}|/)++|(?&{$cssBlock}))*+})";
    }
    public static function cssNestingAtRulesToken(?string $name = null): string
    {
        $bc = self::blockCommentToken();
        $esc = self::cssEscapedString();
        $dqStr = self::doubleQuoteStringToken();
        $sqStr = self::singleQuoteStringToken();
        $cssBlock = self::cssBlockToken();
        $url = self::cssUrlToken();
        $name = $name ?? '[a-zA-Z-]++';
        //language=RegExp
        return "@(?:-[^-]++-)?{$name}\\s*+(?>[^{}@/\\\\'\"u;]++|{$esc}|{$bc}|{$dqStr}|{$sqStr}|{$url}|[/u])*+{$cssBlock}";
    }
    public static function cssStringToken(): string
    {
        $bc = self::blockCommentToken();
        $nestedAtRule = self::cssNestingAtRulesToken();
        $regularAtRule = self::cssRegularAtRulesToken();
        $cssRule = self::cssRuleToken();
        return "(?>\\s++|{$bc}|{$cssRule}|{$nestedAtRule}|{$regularAtRule})*";
    }
}
