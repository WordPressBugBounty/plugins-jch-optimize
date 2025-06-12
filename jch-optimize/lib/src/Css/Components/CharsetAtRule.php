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

use JchOptimize\Core\Css\CssComponents;
use JchOptimize\Core\Exception\InvalidArgumentException;

class CharsetAtRule implements CssComponents
{
    protected string $charset;

    final public function __construct(string $charset = 'utf-8')
    {
        $this->charset = $charset;
    }

    public static function load(string $css): static
    {
        $charsetRegex = self::cssCharsetAtRuleWithCaptureValueToken();

        if (!preg_match("#^{$charsetRegex}$#s", $css, $matches)) {
            throw new InvalidArgumentException('Invalid charset at css: ' . $css);
        }

        $charset = $matches['charset'];

        return new static($charset);
    }

    public function render(): string
    {
        return "@charset \"{$this->charset}\"";
    }

    private static function cssCharsetAtRuleWithCaptureValueToken(): string
    {
        return "@charset\s++['\"](?<charset>.*)['\"];";
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    public function setCharset(string $charset): void
    {
        $this->charset = $charset;
    }
}
