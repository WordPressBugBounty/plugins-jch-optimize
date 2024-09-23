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

use CodeAlfa\RegexTokenizer\Debug\Debug;
use Exception;
use Throwable;
use function assert;
trait Base
{
    use Debug;
    /**
     * Regex token for a string inside double quotes
     *
     * @return string
     */
    //language=RegExp
    public static function doubleQuoteStringToken() : string
    {
        return '"' . self::doubleQuoteStringValueToken() . '(?:"|(?=$))';
    }
    /**
     * Regex token for the value of a string inside double quotes
     *
     * @return string
     */
    //language=RegExp
    public static function doubleQuoteStringValueToken() : string
    {
        return '(?<=")(?>(?:\\\\.)?[^\\\\"]*+)++';
    }
    /**
     * Regex token for a string enclosed by single quotes
     *
     * @return string
     */
    //language=RegExp
    public static function singleQuoteStringToken() : string
    {
        return "'" . self::singleQuoteStringValueToken() . "(?:'|(?=\$))";
    }
    /**
     * Regex token for the value of a string inside single quotes
     *
     * @return string
     */
    //language=RegExp
    public static function singleQuoteStringValueToken() : string
    {
        return "(?<=')(?>(?:\\\\.)?[^\\\\']*+)++";
    }
    /**
     * Regex token for a string enclosed by back ticks
     *
     * @return string
     */
    //language=RegExp
    public static function backTickStringToken() : string
    {
        return '`' . self::backTickStringValueToken() . '(?:`|(?=$))';
    }
    /**
     * Regex token for the value of a string inside back ticks
     *
     * @return string
     */
    //language=RegExp
    public static function backTickStringValueToken() : string
    {
        return '(?<=`)(?>(?:\\\\.)?[^\\\\`]*+)++';
    }
    /**
     * Regex token for any string, optionally capturing the value in a capture group
     *
     * @param bool $shouldCaptureValue Whether value should be captured in a capture group
     *
     * @return string
     */
    //language=RegExp
    public static function stringWithCaptureValueToken(bool $shouldCaptureValue = \false) : string
    {
        $string = '[\'"`]<<' . self::stringValueToken() . '>>[\'"`]';
        return self::prepare($string, $shouldCaptureValue);
    }
    /**
     * Regex token for the value of a string regardless of which quotes are used
     *
     * @return string
     */
    //language=RegExp
    public static function stringValueToken() : string
    {
        return '(?:' . self::doubleQuoteStringValueToken() . '|' . self::singleQuoteStringValueToken() . '|' . self::backTickStringValueToken() . ')';
    }
    /**
     * @param string $regex Regular expression string
     * @param bool $shouldCaptureValue Whether value should be captured
     *
     * @return string
     */
    //language=RegExp
    private static function prepare(string $regex, bool $shouldCaptureValue) : string
    {
        $searchArray = ['<<<', '>>>', '<<', '>>'];
        if ($shouldCaptureValue) {
            return \str_replace($searchArray, ['(?|', ')', '(', ')'], $regex);
        } else {
            return \str_replace($searchArray, ['(?:', ')', '', ''], $regex);
        }
    }
    /**
     * Regex token for block or line comments
     *
     * @return string
     */
    //language=RegExp
    public static function commentToken() : string
    {
        return '(?:' . self::blockCommentToken() . '|' . self::lineCommentToken() . ')';
    }
    /**
     * Regex token for block comment
     *
     * @return string
     */
    //language=RegExp
    public static function blockCommentToken() : string
    {
        return '/\\*(?>\\*?[^*]*+)*?\\*/';
    }
    /**
     * Regex token for line comment
     *
     * @return string
     */
    public static function lineCommentToken() : string
    {
        return '//[^\\r\\n]*+';
    }
    /**
     * Will throw an exception when a PHP preg error is encountered.
     *
     * @return void
     * @throws Exception
     */
    protected static function throwExceptionOnPregError()
    {
        $error = \array_flip(\array_filter(\get_defined_constants(\true)['pcre'], function (string $value) {
            return \substr($value, -6) === '_ERROR';
        }, \ARRAY_FILTER_USE_KEY))[\preg_last_error()];
        if (\preg_last_error() != \PREG_NO_ERROR) {
            throw new Exception($error);
        }
    }
}
