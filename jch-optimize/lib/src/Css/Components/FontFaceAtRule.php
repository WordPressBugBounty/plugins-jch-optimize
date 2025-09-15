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

use function array_walk;
use function implode;
use function preg_replace_callback;
use function trim;

class FontFaceAtRule implements CssComponents
{
    use Css;
    use ModifyCssUrlsTrait;

    protected array $descriptors;

    protected array $src;

    protected string $vendor;

    final public function __construct(array $src = [], array $descriptors = [], string $vendor = '')
    {
        $this->src = $src;
        $this->descriptors = $descriptors;
        $this->vendor = $vendor;
    }

    public static function load(string $css): static
    {
        $fontFaceRegex = self::cssAtFontFaceWithCaptureValueToken();

        if (!preg_match("#^$fontFaceRegex$#s", $css, $matches)) {
            throw new InvalidArgumentException('Invalid font face rule');
        }

        $declarationList = $matches['descriptors'];
        $vendor = $matches['vendor'];

        [$src, $descriptors] = self::getDescriptorsFromDeclarationList($declarationList);

        return new static($src, $descriptors, $vendor);
    }

    private static function getDescriptorsFromDeclarationList(string $declarationList): array
    {
        $descriptorRegex = self::cssFontFaceDescriptorWithCaptureValueToken();

        $src = [];
        $descriptors = [];

        preg_replace_callback("#{$descriptorRegex}#", function ($matches) use (&$src, &$descriptors): string {
            if ($matches['descriptor'] == 'src') {
                $src[] = trim($matches['value']);
            } else {
                $descriptors[$matches['descriptor']] = trim($matches['value']);
            }

            return $matches[0];
        }, $declarationList);

        return [$src, $descriptors];
    }
    public function setDeclarationList(string $declarationList): static
    {
        [$src, $descriptors] = self::getDescriptorsFromDeclarationList($declarationList);

        $this->src = $src;
        $this->descriptors = $descriptors;

        return $this;
    }
    public function render(): string
    {
        $src = '';

        foreach ($this->src as $srcValue) {
            $src .= "src: {$srcValue}; ";
        }

        $declarationList = $this->getDeclarationList();

        return "@{$this->vendor}font-face {{$src}{$declarationList}}";
    }

    private static function cssAtFontFaceWithCaptureValueToken(): string
    {
        return "@(?<vendor>(?:-[^-]++-)?)font-face\s*+{(?<descriptors>.*)}";
    }

    private static function cssFontFaceDescriptorWithCaptureValueToken(): string
    {
        $bc = self::blockCommentToken();
        $dqStr = self::doubleQuoteStringToken();
        $sqStr = self::singleQuoteStringToken();
        $esc = self::cssEscapedString();
        $ws = "(?>\s++|{$bc})*+";

        return "{$ws}(?<descriptor>[a-zA-Z-]++){$ws}"
            . ":(?<value>(?>[^;{}@/'\"\\\\]++|{$bc}|{$dqStr}|{$sqStr}|{$esc}|/)*+)(?:;|$)";
    }

    public function getDeclarationList(): string
    {
        $declarations = $this->descriptors;
        array_walk($declarations, function (&$value, $property) {
            $value = "{$property}: {$value}";
        });

        return implode('; ', $declarations);
    }

    public function setDescriptor(string $descriptor, string $value): static
    {
        $this->descriptors[$descriptor] = $value;

        return $this;
    }

    public function hasDescriptor(string $descriptor): bool
    {
        return isset($this->descriptors[$descriptor]);
    }

    public function getFontFamily(): string
    {
        return trim($this->descriptors['font-family'] ?? '', " \t\n\r\0\x0B'\"");
    }

    public function setFontDisplay(string $value): static
    {
        $this->setDescriptor('font-display', $value);

        return $this;
    }

    public function addSrc(string $value): static
    {
        $this->src[] = $value;

        return $this;
    }

    public function getSrcValues(): array
    {
        return $this->src;
    }

    public function setSrcValue($index, $src): static
    {
        $this->src[$index] = $src;

        return $this;
    }

    public function modifyCssUrls(ModifyCssUrlsProcessor $cssUrlProcessor): void
    {
        foreach ($this->getSrcValues() as $index => $src) {
            $this->setSrcValue(
                $index,
                $this->internalModifyCssUrls($cssUrlProcessor, $src)
            );
        }
    }
}
