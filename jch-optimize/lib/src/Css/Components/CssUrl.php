<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/joomla-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Css\Components;

use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use CodeAlfa\RegexTokenizer\Css;
use JchOptimize\Core\Css\CssComponents;
use JchOptimize\Core\Exception;
use JchOptimize\Core\Uri\Utils;

use function preg_match;

class CssUrl implements CssComponents
{
    use Css;

    protected UriInterface $uri;

    protected string $delimiter;

    protected bool $importantContext = false;

    final public function __construct(UriInterface $uri, string $delimiter = '"')
    {
        $this->uri = $uri;
        $this->delimiter = $delimiter;
    }

    /**
     * @throws Exception\InvalidArgumentException
     */
    public static function load(string $css): static
    {
        $url = self::cssUrlWithCaptureValueToken();

        if (!preg_match("#^{$url}$#s", $css, $matches)) {
            throw new Exception\InvalidArgumentException('Invalid CSS URL');
        }

        $delimiter = $matches['delimiter'] ?? '';
        $urlString = $matches['url'];

        $uri = Utils::uriFor($urlString);

        return new static($uri, $delimiter);
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function setUri(UriInterface $uri): static
    {
        $this->uri = $uri;

        return $this;
    }

    public function render(): string
    {
        return "url({$this->delimiter}{$this->uri}{$this->delimiter})";
    }

    //language=RegExp
    private static function cssUrlWithCaptureValueToken(): string
    {
        $esc = self::cssEscapedString();

        return "url\(\s*+(?<delimiter>['\"]?)(?<url>"
         . "(?<=\")(?>[^\"\\\\]++|{$esc})++|(?<=')(?>[^'\\\\]++|{$esc})++|(?>[^)\\\\]++|{$esc})++"
        . ")['\"]?\s*+\)";
    }

    public function setImportantContext(bool $importantContext): static
    {
        $this->importantContext = $importantContext;

        return $this;
    }

    public function getImportantContext(): bool
    {
        return $this->importantContext;
    }
}
