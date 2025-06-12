<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Css\Components;

use CodeAlfa\RegexTokenizer\Css;
use JchOptimize\Core\Css\CssComponents;
use JchOptimize\Core\Exception\InvalidArgumentException;

class KeyFrames implements CssComponents
{
    use Css;

    protected string $name;

    protected string $rules;

    protected string $vendor;

    final public function __construct(string $name = '', string $rules = '', string $vendor = '')
    {
        $this->name = $name;
        $this->rules = $rules;
        $this->vendor = $vendor;
    }

    public static function load(string $css): static
    {
        $keyframesRegex = self::cssAtKeyFramesWithCaptureValueToken();

        if (!preg_match("#^$keyframesRegex$#s", $css, $matches)) {
            throw new InvalidArgumentException('Invalid CSS keyframes.');
        }

        $name = trim($matches['name'], '\'"');
        $rules = $matches['rules'];

        return new static($name, $rules);
    }

    public function render(): string
    {
        return "@{$this->vendor}keyframes {$this->name} {{$this->rules}}";
    }

    private static function cssAtKeyFramesWithCaptureValueToken(): string
    {
        $bc = self::blockCommentToken();
        $esc = self::cssEscapedString();
        $dqStr = self::doubleQuoteStringToken();
        $sqStr = self::singleQuoteStringToken();
        $s = "(?>\s++|{$bc})*+";
        $ident = "[a-zA-z0-9_-]++";

        //language=RegExp
        return "@(?<vendor>(?:-[^-]++-)?)keyframes\s++{$s}"
        . "(?<name>{$dqStr}|{$sqStr}|(?>{$ident}|{$bc}|{$esc})++){$s}{(?<rules>.*)}";
    }

    public function getName(): string
    {
        return $this->name;
    }
}
