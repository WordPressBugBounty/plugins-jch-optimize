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
class Json extends \CodeAlfa\Minify\Base
{
    use \CodeAlfa\RegexTokenizer\Js;
    public string $json;
    /**
     * @param   string  $json
     *
     * @return string
     */
    public static function optimize(string $json) : string
    {
        $obj = new \CodeAlfa\Minify\Json($json);
        try {
            return $obj->_optimize();
        } catch (Exception $e) {
            return $obj->json;
        }
    }
    protected function __construct(string $json)
    {
        $this->json = $json;
        parent::__construct();
    }
    /**
     *
     * @return string
     *
     * @throws Exception
     */
    private function _optimize() : string
    {
        //regex for double-quoted strings
        $s1 = self::doubleQuoteStringToken();
        //regex for single quoted string
        $s2 = self::singleQuoteStringToken();
        //regex for block comments
        $b = self::blockCommentToken();
        //regex for line comments
        $c = self::lineCommentToken();
        //regex for HTML comments
        $h = self::jsHtmlCommentToken();
        //remove all comments
        $rx = "#(?>[^/\"'<]*+(?:{$s1}|{$s2})?)*?\\K(?>{$b}|{$c}|{$h}|\$)#si";
        $this->json = $this->_replace($rx, '', $this->json, '1');
        //remove whitespaces around :,{}
        $rx = "#(?>[^\"'\\s]*+(?:{$s1}|{$s2})?)*?\\K(?>\\s++(?=[:,{}\\[\\]])|(?<=[:,{}\\[\\]])\\s++|\$)#s";
        $this->json = $this->_replace($rx, '', $this->json, '2');
        return $this->json;
    }
}
