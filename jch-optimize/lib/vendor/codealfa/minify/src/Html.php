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
use function call_user_func;
use function is_callable;
class Html extends \CodeAlfa\Minify\Base
{
    use \CodeAlfa\RegexTokenizer\Html;
    public string $html = '';
    /**
     * @psalm-var array{isXhtml: bool, isHtml5: bool, jsMinifier: callable|null, minifyLevel: int, jsonMinifier: callable|null, cssMinifier: callable|null}
     */
    protected array $options;
    /**
     * @param string $content
     * @param string $type
     *
     * @return string
     */
    public static function cleanScript(string $content, string $type) : string
    {
        $s1 = self::doubleQuoteStringToken();
        $s2 = self::singleQuoteStringToken();
        $b = self::blockCommentToken();
        $l = self::lineCommentToken();
        $c = self::htmlCommentToken();
        if ($type == 'css') {
            return \preg_replace("#(?>[<\\]\\-]?[^'\"<\\]\\-/]*+(?>{$s1}|{$s2}|{$b}|{$l}|/)?)*?\\K(?:{$c}|\$)#i", '', $content);
        } else {
            return \CodeAlfa\Minify\Js::optimize($content, array('prepareOnly' => \true));
        }
    }
    /**
     * "Minify" an HTML page
     *
     * @param string $html
     * @param $options
     * @psalm-param array{isXhtml?: bool, isHtml5?: bool, jsMinifier?: callable, jsonMinifier?: callable, cssMinifier?: callable, minifyLevel?: int}|null $options
     *
     * @return string
     */
    public static function optimize(string $html, $options = null) : string
    {
        $min = new \CodeAlfa\Minify\Html($html, $options);
        try {
            return $min->_optimize();
        } catch (Exception $e) {
            return $min->html;
        }
    }
    /**
     * @param string $html
     * @param $options
     * @psalm-param array{isXhtml?: bool, isHtml5?: bool, jsMinifier?: callable, jsonMinifier?: callable, cssMinifier?: callable, minifyLevel?: int}|null $options
     */
    protected function __construct(string $html, $options)
    {
        $this->html = $html;
        $paramOptions = ['isXhtml' => \false, 'isHtml5' => \false, 'minifyLevel' => 0, 'cssMinifier' => null, 'jsMinifier' => null, 'jsonMinifier' => null];
        if ($options) {
            $paramOptions = \array_merge($paramOptions, $options);
        }
        $this->options = $paramOptions;
        parent::__construct();
    }
    /**
     * Minify the markup given in the constructor
     *
     * @return string
     * @throws Exception
     */
    private function _optimize() : string
    {
        $x = self::htmlCommentToken();
        $s1 = self::doubleQuoteStringToken();
        $s2 = self::singleQuoteStringToken();
        $a = self::htmlAttributeWithCaptureValueToken();
        //Regex for escape elements
        $pr = self::htmlElementToken('pre');
        $sc = self::htmlElementToken('script');
        $st = self::htmlElementToken('style');
        $tx = self::htmlElementToken('textarea');
        if ($this->options['minifyLevel'] > 0) {
            //Remove comments (not containing IE conditional comments)
            $rx = "#(?><?[^<]*+(?>{$pr}|{$sc}|{$st}|{$tx}|<!--\\[(?><?[^<]*+)*?" . "<!\\s*\\[(?>-?[^-]*+)*?--!?>|<!DOCTYPE[^>]++>)?)*?\\K(?:{$x}|\$)#i";
            $this->html = $this->_replace($rx, '', $this->html, 'html1');
        }
        //Reduce runs of whitespace outside all elements to one
        $rx = "#(?>[^<]*+(?:{$pr}|{$sc}|{$st}|{$tx}|{$x}|<(?>[^>=]*+(?:=\\s*+(?:{$s1}|{$s2}|['\"])?|(?=>)))*?>)?)*?\\K" . '(?:[\\t\\f ]++(?=[\\r\\n]\\s*+<)|(?>\\r?\\n|\\r)\\K\\s++(?=<)|[\\t\\f]++(?=[ ]\\s*+<)|[\\t\\f]\\K\\s*+(?=<)|[ ]\\K\\s*+(?=<)|$)#i';
        $this->html = $this->_replace($rx, '', $this->html, 'html2');
        //Minify scripts
        //invalid scripts
        $nsc = "<script\\b(?=(?>\\s*+{$a})*?\\s*+type\\s*+=\\s*+(?![\"']?(?:text|application)/(?:javascript|[^'\"\\s>]*?json)))[^<>]*+>(?><?[^<]*+)*?</\\s*+script\\s*+>";
        //invalid styles
        $nst = "<style\\b(?=(?>\\s*+{$a})*?\\s*+type\\s*+=\\s*+(?![\"']?(?:text|(?:css|stylesheet))))[^<>]*+>(?><?[^<]*+)*?</\\s*+style\\s*>";
        $rx = "#(?><?[^<]*+(?:{$x}|{$nsc}|{$nst})?)*?\\K" . "(?:(<script\\b(?!(?>\\s*+{$a})*?\\s*+type\\s*+=\\s*+(?![\"']?(?:text|application)/(?:javascript|[^'\"\\s>]*?json)))[^<>]*+>)((?><?[^<]*+)*?)(</\\s*+script\\s*+>)|" . "(<style\\b(?!(?>\\s*+{$a})*?\\s*+type\\s*+=\\s*+(?![\"']?text/(?:css|stylesheet)))[^<>]*+>)((?><?[^<]*+)*?)(</\\s*+style\\s*+>)|\$)#i";
        $this->html = $this->_replace($rx, '', $this->html, 'html3', array($this, '_minifyCB'));
        if ($this->options['minifyLevel'] < 1) {
            return \trim($this->html);
        }
        //Replace line feed with space (legacy)
        $rx = "#(?>[^<]*+(?:{$pr}|{$sc}|{$st}|{$tx}|{$x}|<(?>[^>=]*+(?:=\\s*+(?:{$s1}|{$s2}|['\"])?|(?=>)))*?>)?)*?\\K" . '(?:[\\r\\n\\t\\f]++(?=<)|$)#i';
        $this->html = $this->_replace($rx, ' ', $this->html, 'html4');
        // remove ws around block elements preserving space around inline elements
        //block/undisplayed elements
        $b = 'address|article|aside|audio|body|blockquote|canvas|dd|div|dl' . '|fieldset|figcaption|figure|footer|form|h[1-6]|head|header|hgroup|html|noscript|ol|output|p' . '|pre|section|style|table|title|tfoot|ul|video';
        //self closing block/undisplayed elements
        $b2 = 'base|meta|link|hr';
        //inline elements
        $i = 'b|big|i|small|tt' . '|abbr|acronym|cite|code|dfn|em|kbd|strong|samp|var' . '|a|bdo|br|map|object|q|script|span|sub|sup' . '|button|label|select|textarea';
        //self closing inline elements
        $i2 = 'img|input';
        $rx = "#(?>\\s*+(?:{$pr}|{$sc}|{$st}|{$tx}|{$x}|<(?:(?>{$i})\\b[^>]*+>|(?:/(?>{$i})\\b>|(?>{$i2})\\b[^>]*+>)\\s*+)|<[^>]*+>)|[^<]++)*?\\K" . "(?:\\s++(?=<(?>{$b}|{$b2})\\b)|(?:</(?>{$b})\\b>|<(?>{$b2})\\b[^>]*+>)\\K\\s++(?!<(?>{$i}|{$i2})\\b)|\$)#i";
        $this->html = $this->_replace($rx, '', $this->html, 'html5');
        //Replace runs of whitespace inside elements with single space escaping pre, textarea, scripts and style elements
        //elements to escape
        $e = 'pre|script|style|textarea';
        $rx = "#(?>[^<]*+(?:{$pr}|{$sc}|{$st}|{$tx}|{$x}|<[^>]++>[^<]*+))*?(?:(?:<(?!{$e}|!)[^>]*+>)?(?>\\s?[^\\s<]*+)*?\\K\\s{2,}|\\K\$)#i";
        $this->html = $this->_replace($rx, ' ', $this->html, 'html6');
        //Remove additional ws around attributes
        $rx = "#(?>\\s?(?>[^<>]*+(?:<!(?!DOCTYPE)(?>>?[^>]*+)*?>[^<>]*+)?<|(?=[^<>]++>)[^\\s>'\"]++(?>{$s1}|{$s2})?|[^<]*+))*?\\K" . "(?>\\s\\s++|\$)#i";
        $this->html = $this->_replace($rx, ' ', $this->html, 'html7');
        if ($this->options['minifyLevel'] < 2) {
            return \trim($this->html);
        }
        //remove redundant attributes
        $rx = "#(?:(?=[^<>]++>)|(?><?[^<]*+(?>{$x}|{$nsc}|{$nst}|<(?!(?:script|style|link)|/html>))?)*?" . "<(?:(?:script|style|link)|/html>))(?>[ ]?[^ >]*+)*?\\K" . '(?: (?:type|language)=["\']?(?:(?:text|application)/(?:javascript|css)|javascript)["\']?|[^<]*+\\K$)#i';
        $this->html = $this->_replace($rx, '', $this->html, 'html8');
        $j = '<input type="hidden" name="[0-9a-f]{32}" value="1" />';
        //Remove quotes from selected attributes
        if ($this->options['isHtml5']) {
            $ns1 = '"[^"\'`=<>\\s]*+(?:[\'`=<>\\s]|(?<=\\\\)")(?>(?:(?<=\\\\)")?[^"]*+)*?(?<!\\\\)"';
            $ns2 = "'[^'\"`=<>\\s]*+(?:[\"`=<>\\s]|(?<=\\\\)')(?>(?:(?<=\\\\)')?[^']*+)*?(?<!\\\\)'";
            $rx = "#(?:(?=[^>]*+>)|<[a-z0-9]++ )" . "(?>[=]?[^=><]*+(?:=(?:{$ns1}|{$ns2})|>(?>[^<]*+(?:{$j}|{$x}|{$nsc}|{$nst}|<(?![a-z0-9]++ ))?)*?(?:<[a-z0-9]++ |\$))?)*?" . "(?:=\\K([\"'])([^\"'`=<>\\s]++)\\g{1}[ ]?|\\K\$)#i";
            $this->html = $this->_replace($rx, '$2 ', $this->html, 'html9');
        }
        //Remove last whitespace in open tag
        $rx = "#(?>[^<]*+(?:{$j}|{$x}|{$nsc}|{$nst}|<(?![a-z0-9]++))?)*?(?:<[a-z0-9]++(?>\\s*+[^\\s>]++)*?\\K" . "(?:\\s*+(?=>)|(?<=[\"'])\\s++(?=/>))|\$\\K)#i";
        $this->html = $this->_replace($rx, '', $this->html, 'html10');
        return \trim($this->html);
    }
    /**
     *
     * @param string[] $m
     *
     * @return string
     */
    protected function _minifyCB(array $m) : string
    {
        if ($m[0] == '') {
            return $m[0];
        }
        if (\strpos($m[0], 'var google_conversion') !== \false) {
            return $m[0];
        }
        $openTag = isset($m[1]) && $m[1] != '' ? $m[1] : (isset($m[4]) && $m[4] != '' ? $m[4] : '');
        $content = isset($m[2]) && $m[2] != '' ? $m[2] : (isset($m[5]) && $m[5] != '' ? $m[5] : '');
        $closeTag = isset($m[3]) && $m[3] != '' ? $m[3] : (isset($m[6]) && $m[6] != '' ? $m[6] : '');
        if (\trim($content) == '') {
            return $m[0];
        }
        $type = \stripos($openTag, 'script') == 1 ? \stripos($openTag, 'json') !== \false ? 'json' : 'js' : 'css';
        if (is_callable($this->options[$type . 'Minifier'])) {
            // minify
            /** @psalm-suppress PossiblyNullArgument $content */
            $content = $this->_callMinifier($this->options[$type . 'Minifier'], $content);
            return $this->_needsCdata($content, $type) ? "{$openTag}/*<![CDATA[*/{$content}/*]]>*/{$closeTag}" : "{$openTag}{$content}{$closeTag}";
        } else {
            return $m[0];
        }
    }
    /**
     *
     * @param callable $minifier
     * @param string $content
     *
     * @return string
     */
    protected function _callMinifier(callable $minifier, string $content) : string
    {
        return (string) call_user_func($minifier, $content);
    }
    /**
     *
     * @param string $str
     * @param string $type
     *
     * @return bool
     */
    protected function _needsCdata(string $str, string $type) : bool
    {
        return $this->options['isXhtml'] && $type == 'js' && \preg_match('#(?:[<&]|\\-\\-|\\]\\]>)#', $str);
    }
}
