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
use JchOptimize\Core\Css\ModifyCssUrlsProcessor;
use JchOptimize\Core\Css\ModifyCssUrlsTrait;
use JchOptimize\Core\Exception\InvalidArgumentException;

use function preg_match_all;
use function preg_replace_callback;
use function rtrim;
use function str_contains;

use const PREG_SET_ORDER;

class CssRule implements CssComponents
{
    use Css;
    use ModifyCssUrlsTrait;

    protected string $selectorList;

    protected string $declarationList;

    final public function __construct($selectorList = '', $declarationList = '')
    {
        $this->selectorList = $selectorList;
        $this->declarationList = $declarationList;
    }

    public static function load(string $css): static
    {
        $cssRuleRegex = self::cssRuleWithCaptureValueToken();

        if (!preg_match("#^{$cssRuleRegex}$#s", $css, $matches)) {
            throw new InvalidArgumentException('Invalid CSS rule: ' . $css);
        }

        $selectorList = $matches['selectorlist'];
        $declarationList = $matches['declarationlist'];

        return new static($selectorList, $declarationList);
    }

    public function render(): string
    {
        if ($this->selectorList !== '') {
            return "{$this->selectorList}{{$this->declarationList}}";
        }

        return $this->declarationList;
    }

    private static function cssRuleWithCaptureValueToken(): string
    {
        $selectors = self::cssSelectorListToken();

        return "(?<selectorlist>{$selectors}){(?<declarationlist>.*)}";
    }

    public function getSelectorList(): string
    {
        return $this->selectorList;
    }

    public function setSelectorList(string $selectorList): static
    {
        $this->selectorList = $selectorList;

        return $this;
    }

    public function appendSelectorList(string $selectorList): static
    {
        $this->selectorList .= ", {$selectorList}";

        return $this;
    }

    public function getDeclarationList(): string
    {
        return $this->declarationList;
    }

    public function setDeclarationList(string $declarationList): static
    {
        $this->declarationList = $declarationList;

        return $this;
    }

    public function prependDeclarationList(string $cssRule): static
    {
        $this->declarationList = $cssRule . $this->declarationList;

        return $this;
    }

    public function appendDeclarationList(string $cssRule): static
    {
        $this->declarationList = $this->appendSemiColon($this->declarationList) . $cssRule;

        return $this;
    }

    private function appendSemiColon(string $declaration): string
    {
        $declaration = rtrim($declaration, " ;\n\r\t\v\x00");

        if ($declaration !== '' && !str_ends_with($declaration, '}')) {
            $declaration .= ';';
        }

        return $declaration;
    }

    public function modifyCssUrls(ModifyCssUrlsProcessor $cssUrlProcessor): void
    {
        $this->setDeclarationList(
            $this->parseDeclarationList($cssUrlProcessor, $this->getDeclarationList())
        );

        $cssUrlProcessor->postProcessModifiedCssComponent($this);
    }

    public function isModified(): bool
    {
        return $this->modified;
    }

    public function getCssUrls(): array
    {
        $regex = self::cssUrlStringToken();

        preg_match_all("#{$regex}#", $this->getDeclarationList(), $matches, PREG_SET_ORDER);

        return array_map(
            fn($match) => !empty($match[0]) ? CssUrl::load($match[0]) : '',
            array_filter($matches)
        );
    }

    private function parseDeclarationList(ModifyCssUrlsProcessor $cssUrlsProcessor, string $declarationList): string
    {
        $dec = self::cssDeclarationToken();
        $cssRule = self::cssRuleToken();
        $atRule = self::cssNestingAtRulesToken();
        $comment = self::blockCommentToken();

        return preg_replace_callback(
            "#{$comment}|(?<declarations>(?>{$dec})++)|(?<cssRule>{$cssRule})|(?<atRule>{$atRule})#ix",
            function ($matches) use ($cssUrlsProcessor) {
                if (!empty($matches['declarations'])) {
                    if (str_contains($matches['declarations'], 'url(')) {
                        return $this->internalModifyCssUrls($cssUrlsProcessor, $matches['declarations']);
                    } else {
                        return $matches['declarations'];
                    }
                }

                if (!empty($matches['cssRule'])) {
                    try {
                        $cssRule = CssRule::load($matches['cssRule']);
                        $cssRule->modifyCssUrls($cssUrlsProcessor);

                        return $cssRule->render();
                    } catch (InvalidArgumentException) {
                        return $matches['cssRule'];
                    }
                }

                if (!empty($matches['atRule'])) {
                    try {
                        return $this->parseAtRulesForCssUrls(
                            $cssUrlsProcessor,
                            NestingAtRule::load($matches['atRule'])
                        );
                    } catch (InvalidArgumentException) {
                        return $matches['atRule'];
                    }
                }

                return $matches[0];
            },
            $declarationList
        );
    }

    private function parseAtRulesForCssUrls(ModifyCssUrlsProcessor $cssUrlsProcessor, NestingAtRule $atRule): string
    {
        return $atRule->setCssRuleList(
            $this->parseDeclarationList($cssUrlsProcessor, $atRule->getCssRuleList())
        )->render();
    }
}
