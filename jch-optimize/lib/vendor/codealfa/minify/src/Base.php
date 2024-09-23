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
use function is_string;
abstract class Base
{
    use \CodeAlfa\RegexTokenizer\Base;
    protected function __construct()
    {
        if (!\defined('CODEALFA_MINIFY_CONFIGURED')) {
            \ini_set('pcre.backtrack_limit', '1000000');
            \ini_set('pcre.recursion_limit', '1000000');
            \ini_set('pcre.jit', '0');
            \define('CODEALFA_MINIFY_CONFIGURED', 1);
        }
    }
    /**
     * @staticvar bool $tm
     *
     * @param string $regex
     * @param string $replacement
     * @param string $code
     * @param mixed $regexNum
     * @param callable|null $callback
     * @return string
     * @throws Exception
     */
    protected function _replace(string $regex, string $replacement, string $code, $regexNum, ?callable $callback = null) : string
    {
        static $tm = \false;
        if ($tm === \false) {
            $this->_debug('', '');
            $tm = \true;
        }
        if (empty($callback)) {
            $op_code = \preg_replace($regex, $replacement, $code);
        } else {
            $op_code = \preg_replace_callback($regex, $callback, $code);
        }
        $this->_debug($regex, $code, $regexNum);
        self::throwExceptionOnPregError();
        return $op_code;
    }
}
