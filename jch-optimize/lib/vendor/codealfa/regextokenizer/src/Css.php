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
    /**
     * Regex token for a CSS ident
     *
     * @return string
     */
    //language=RegExp
    public static function cssIdentToken() : string
    {
        return '(?:\\\\.|[a-z0-9_-]++\\s++)';
    }
    /**
     * Regex token for a CSS url, optionally capturing the value in a capture group
     *
     * @param   bool  $shouldCaptureValue Whether to capture the value in a capture group
     *
     * @return string
     */
    //language=RegExp
    public static function cssUrlWithCaptureValueToken(bool $shouldCaptureValue = \false) : string
    {
        $cssUrl = '(?:url\\(|(?<=url)\\()(?:\\s*+[\'"])?<<' . self::cssUrlValueToken() . '>>(?:[\'"]\\s*+)?\\)';
        return self::prepare($cssUrl, $shouldCaptureValue);
    }
    /**
     * Regex token for a CSS url value
     *
     * @return string
     */
    //language=RegExp
    public static function cssUrlValueToken() : string
    {
        return '(?:' . self::stringValueToken() . '|' . self::cssUnquotedUrlValueToken() . ')';
    }
    /**
     * Regex token for an unquoted CSS url value
     *
     * @return string
     */
    //language=RegExp
    public static function cssUnquotedUrlValueToken() : string
    {
        return '(?<=url\\()(?>\\s*+(?:\\\\.)?[^\\\\()\\s\'"]*+)++';
    }
}
