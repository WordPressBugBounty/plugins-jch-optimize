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
use JchOptimize\Core\Exception\InvalidArgumentException;
use JchOptimize\Core\Uri\Utils;

class ImportAtRule implements CssComponents
{
    use Css;

    final public function __construct(
        protected CssUrl|UriInterface|null $url = null,
        protected string $layer = '',
        protected string $supports = '',
        protected string $mediaQueriesList = ''
    ) {
    }

    public static function load(string $css): static
    {
        $importRegex = self::cssAtImportWithCaptureValueToken();

        if (!preg_match("#^$importRegex$#ix", $css, $matches)) {
            throw new InvalidArgumentException('Invalid Import At Rule Css');
        }

        try {
            $url = CssUrl::load($matches['url']);
        } catch (InvalidArgumentException) {
            $url = Utils::uriFor(trim($matches['url'], '\'"'));
        }

        return new static(
            $url,
            $matches['layer'] ?? '',
            $matches['supports'] ?? '',
            $matches['mediaquerieslist'] ?? ''
        );
    }

    public function render(): string
    {
        if ($this->url instanceof CssUrl) {
            $url = $this->url->render();
        } else {
            $url = "\"{$this->url}\"";
        }

        if (!empty($this->layer)) {
            $layer = ' ' . $this->layer;
        } else {
            $layer = '';
        }

        if (!empty($this->supports)) {
            $supports = ' ' . $this->supports;
        } else {
            $supports = '';
        }

        if (!empty($this->mediaQueriesList)) {
            $mediaQueriesList = ' ' . $this->mediaQueriesList;
        } else {
            $mediaQueriesList = '';
        }

        return "@import {$url}{$layer}{$supports}{$mediaQueriesList};";
    }

    public function getUri(): ?UriInterface
    {
        if ($this->url instanceof CssUrl) {
            return $this->url->getUri();
        }

        return $this->url;
    }

    public function setUri(UriInterface $uri): static
    {
        if ($this->url instanceof CssUrl) {
            $this->url->setUri($uri);
        } else {
            $this->url = $uri;
        }

        return $this;
    }

    public function getLayer(): string
    {
        return $this->layer;
    }

    public function setLayer(string $layer): void
    {
        $this->layer = $layer;
    }

    public function getSupports(): string
    {
        return $this->supports;
    }

    public function setSupports(string $supports): void
    {
        $this->supports = $supports;
    }

    public function getMediaQueriesList(): string
    {
        return $this->mediaQueriesList;
    }

    public function setMediaQueriesList(string $mediaQueriesList): void
    {
        $this->mediaQueriesList = $mediaQueriesList;
    }

    public function getUrl(): UriInterface|CssUrl|null
    {
        return $this->url;
    }

    public function setUrl(UriInterface|CssUrl $url): void
    {
        $this->url = $url;
    }

    private static function cssAtImportWithCaptureValueToken(): string
    {
        $url = self::cssUrlToken();
        $dqStr = self::doubleQuoteStringToken();
        $sqStr = self::singleQuoteStringToken();

        return "@import\s++(?<url>{$url}|{$dqStr}|{$sqStr})\s*+"
            . "(?<layer>layer(?:\([^);]++\))?)?\s*+"
            . "(?<supports>supports(?<supportsconditions>\((?>[^();]++|(?&supportsconditions))*+\)))?\s*+"
            . "(?<mediaquerieslist>[^;]++)?;";
    }
}
