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

namespace JchOptimize\Core\Css\Components;

use CodeAlfa\RegexTokenizer\Css;
use JchOptimize\Core\Css\CssComponents;
use JchOptimize\Core\Css\Parser;
use JchOptimize\Core\Exception\InvalidArgumentException;

class InvalidCssComponent implements CssComponents
{
    use Css;

    final public function __construct()
    {
    }

    public static function load(string $css): static
    {
        $regex = self::invalidCssComponentToken();

        if (!preg_match("#{$regex}#", $css)) {
            throw new InvalidArgumentException('Css not invalid');
        }

        return new static();
    }

    public function render(): string
    {
        return '';
    }

    private static function invalidCssComponentToken(): string
    {
        $bc = self::blockCommentToken();
        $cssRule = self::cssRuleToken();
        $regularAtRule = self::cssRegularAtRulesToken();
        $nestingAtRule = self::cssNestingAtRulesToken();
        $invalidCss = Parser::cssInvalidCssToken();

        return "(?!\s++|{$bc}|$cssRule|{$regularAtRule}|{$nestingAtRule}){$invalidCss}";
    }
}
