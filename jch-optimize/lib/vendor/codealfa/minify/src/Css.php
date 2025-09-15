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
    public static function optimize(string $css): string
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
    private function _optimize(): string
    {
        $s1 = self::doubleQuoteStringToken();
        $s2 = self::singleQuoteStringToken();
        $u = self::cssUrlToken();
        $b = self::blockCommentToken();
        $sel = self::cssSelectorListToken();
        // Remove all comments
        // language=RegExp
        $rx = "(?>[^/\"'u]++|{$s1}|{$s2}|{$u}|[/\"'u])*?\\K(?>{$b}|\$)";
        $this->css = $this->_replace("#{$rx}#si", '', $this->css, 'css1');
        // remove ws around , ; : { } in CSS Declarations and media queries
        // language=RegExp
        $rx = "(?>(?<=.)[^{}\\s\"'u;]++|(?:^|[{}/;])\\s*+{$sel}\\{|{$s1}|{$s2}|{$u}|[{}\\s\"'u;]|^.)*?" . "\\K(?:\\s++(?=[,;:{}])|(?<=[,;:{}])\\s++|\$)";
        $this->css = $this->_replace("#{$rx}#si", '', $this->css, 'css2');
        //remove ws around , + > ~ { } in selectors
        //language=RegExp
        $rx = "(?>[^{}\\s\"'u]++|\\{[^{}]++\\}|{$s1}|{$s2}|{$u}|[{}\\s\"'u])*?\\K(?:\\s++(?=[,+>~{}])|(?<=[,+>~{};])\\s++|\$)";
        $this->css = $this->_replace("#{$rx}#si", '', $this->css, 'css3');
        //remove last ; in block
        //language=RegExp
        $rx = "(?>[^;\"'u]++|{$s1}|{$s2}|{$u}|[;\"'u])*?\\K(?:;(?=\\s*+})|\$)";
        $this->css = $this->_replace("#{$rx}#si", '', $this->css, 'css4');
        // remove ws inside urls
        //language=RegExp
        $rx = "(?>[^('\"]++|{$s1}|{$s2}|\\()*?(?:(?<=\\burl)\\(\\K\\s*+({$s1}|{$s2}|\\S++)\\s*+(?=\\))|\\K\$)";
        $this->css = $this->_replace("#{$rx}#si", '$1', $this->css, 'css5');
        // minimize hex colors
        //language=RegExp
        $rx = "(?>[^\\#\"'u]++|{$s1}|{$s2}|{$u}|[\\#\"'u])*?" . "(?:\\#\\K([a-f\\d])\\g{1}([a-f\\d])\\g{2}([a-f\\d])\\g{3}\\b|\\K\$)";
        $this->css = $this->_replace("#{$rx}#si", '$1$2$3', $this->css, 'css6');
        // reduce remaining ws to single space
        //language=RegExp
        $rx = "(?>[^\\s'\"u]++|{$s1}|{$s2}|{$u}|[\\s'\"u])*?\\K(?:\\s\\s++|\$)";
        $this->css = $this->_replace("#{$rx}#si", ' ', $this->css, 'css7');
        return trim($this->css);
    }
}
