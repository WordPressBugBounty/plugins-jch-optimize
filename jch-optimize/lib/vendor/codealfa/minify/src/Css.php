<?php

/**
 * @package   codealfa/minify
 * @author    Samuel Marshall <sdmarshall73@gmail.com>
 * @copyright Copyright (c) 2020 Samuel Marshall
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */
namespace CodeAlfa\Minify;

use Exception;
class Css extends \CodeAlfa\Minify\Base
{
    use \CodeAlfa\RegexTokenizer\Css;
    public string $css;
    /**
     * Minify a CSS string
     *
     * @param   string  $css
     *
     * @return string
     */
    public static function optimize(string $css) : string
    {
        $obj = new \CodeAlfa\Minify\Css($css);
        try {
            return $obj->_optimize();
        } catch (Exception $e) {
            return $obj->css;
        }
    }
    private function __construct(string $css)
    {
        $this->css = $css;
        parent::__construct();
    }
    /**
     * Minify a CSS string
     *
     * @return string
     * @throws Exception
     */
    private function _optimize() : string
    {
        $s1 = self::doubleQuoteStringToken();
        $s2 = self::singleQuoteStringToken();
        $es = $s1 . '|' . $s2;
        $s = '(?<!\\\\)(?:' . $es . ')|[\'"]';
        $u = self::cssUrlWithCaptureValueToken();
        $e = '(?<!\\\\)(?:' . $es . '|' . $u . ')|[\'"(]';
        $b = self::blockCommentToken();
        //$c = self::LINE_COMMENT();
        // Remove all comments
        $rx = "#(?>/?[^/\"'(]*+(?:{$e})?)*?\\K(?>{$b}|\$)#s";
        $this->css = $this->_replace($rx, '', $this->css, 'css1');
        // remove ws around , ; : { } in CSS Declarations and media queries
        $rx = "#(?>(?:[{};]|^)[^{}@;]*+{|(?:(?<![,;:{}])\\s++(?![,;:{}]))?[^\\s{};\"'(]*+(?:{$e}|[{};])?)+?\\K" . "(?:\\s++(?=[,;:{}])|(?<=[,;:{}])\\s++|\\K\$)#s";
        $this->css = $this->_replace($rx, '', $this->css, 'css2');
        //remove ws around , + > ~ { } in selectors
        $rx = "#(?>(?:(?<![,+>~{}])\\s++(?![,+>~{}]))?[^\\s{\"'(]*+(?:{[^{}]++}|{|{$e})?)*?\\K" . "(?:\\s++(?=[,+>~{}])|(?<=[,+>~{};])\\s++|\$\\K)#s";
        $this->css = $this->_replace($rx, '', $this->css, 'css3');
        //remove last ; in block
        $rx = "#(?>(?:;(?!}))?[^;\"'(]*+(?:{$e})?)*?\\K(?:;(?=})|\$\\K)#s";
        $this->css = $this->_replace($rx, '', $this->css, 'css4');
        // remove ws inside urls
        $rx = "#(?>\\(?[^\"'(]*+(?:{$s})?)*?(?:(?<=\\burl)\\(\\K\\s++|\\G" . "(?(?=[\"'])['\"][^'\"]++['\"]|[^\\s]++)\\K\\s++(?=\\))|\$\\K)#s";
        $this->css = $this->_replace($rx, '', $this->css, 'css5');
        // minimize hex colors
        $rx = "#(?>\\#?[^\\#\"'(]*+(?:{$e})?)*?(?:(?<!=)\\#\\K" . "([a-f\\d])\\g{1}([a-f\\d])\\g{2}([a-f\\d])\\g{3}(?=[\\s;}])|\$\\K)#is";
        $this->css = $this->_replace($rx, '$1$2$3', $this->css, 'css6');
        // reduce remaining ws to single space
        $rx = "#(?>[^\\s'\"(]*+(?:{$e}|\\s(?!\\s))?)*?\\K(?:\\s\\s++|\$)#s";
        $this->css = $this->_replace($rx, ' ', $this->css, 'css7');
        return \trim($this->css);
    }
}
