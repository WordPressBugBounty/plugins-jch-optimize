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

class NestingAtRule implements CssComponents
{
    use Css;

    private string $identifier;

    private string $rule;

    private string $cssRuleList;

    private string $vendor;

    final public function __construct(
        string $identifier = '',
        string $rule = '',
        string $cssRuleList = '',
        string $vendor = ''
    ) {
        $this->identifier = $identifier;
        $this->rule = $rule;
        $this->cssRuleList = $cssRuleList;
        $this->vendor = $vendor;
    }

    public static function load(string $css): static
    {
        $nestedAtRuleRegex = self::cssNestingAtRuleWithCaptureGroupToken();

        if (!preg_match("#^{$nestedAtRuleRegex}$#", $css, $matches)) {
            throw new InvalidArgumentException('Invalid nested at-rule rule: ');
        }

        $identifier = $matches['identifier'];
        $vendor = $matches['vendor'];
        $rule = $matches['rule'];
        $cssRuleList = $matches['cssRuleList'];

        return new static($identifier, $rule, $cssRuleList, $vendor);
    }

    public function render(): string
    {
        return "@{$this->vendor}{$this->identifier} {$this->rule} {{$this->cssRuleList}}";
    }


    private static function cssNestingAtRuleWithCaptureGroupToken(): string
    {
        $esc = self::cssEscapedString();
        $dqStr = self::doubleQuoteStringToken();
        $sqStr = self::singleQuoteStringToken();
        $bc = self::blockCommentToken();
        $url = self::cssUrlToken();

        return "@(?<vendor>(?:-[^-]++-)?)(?<identifier>[a-zA-Z-]++)\s*+"
        . "(?<rule>(?>[^{}@/\\\\'\";\su]++|{$bc}|{$esc}|{$dqStr}|{$sqStr}|{$url}|[/u]|\s++)*?)\s*+"
        . "(?P<cssBlock>{(?<cssRuleList>(?>(?:[^{}/\\\\'\"]++|{$bc}|{$esc}|{$dqStr}|{$sqStr}|/)++|(?&cssBlock))*+)})";
    }

    public function setIdentifier(string $identifier): static
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setRule(string $rule): static
    {
        $this->rule = $rule;

        return $this;
    }

    public function getRule(): string
    {
        return $this->rule;
    }

    public function setCssRuleList(string $cssRuleList): static
    {
        $this->cssRuleList = $cssRuleList;

        return $this;
    }

    public function getCssRuleList(): string
    {
        return $this->cssRuleList;
    }

    public function setVendor(string $vendor): static
    {
        $this->vendor = $vendor;

        return $this;
    }

    public function getVendor(): string
    {
        return $this->vendor;
    }
}
