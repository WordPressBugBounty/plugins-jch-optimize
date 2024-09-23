<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads.
 *
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Html\Callbacks;

use _JchOptimizeVendor\GuzzleHttp\Psr7\UriResolver;
use _JchOptimizeVendor\Joomla\DI\Container;
use _JchOptimizeVendor\Psr\Http\Message\UriInterface;
use JchOptimize\Core\Cdn as CdnCore;
use JchOptimize\Core\Css\Parser as CssParser;
use JchOptimize\Core\Exception\PregErrorException;
use JchOptimize\Core\Exception\PropertyNotFoundException;
use JchOptimize\Core\Html\Elements\BaseElement;
use JchOptimize\Core\Html\Elements\Style;
use JchOptimize\Core\Html\Elements\Video;
use JchOptimize\Core\Html\HtmlElementBuilder;
use JchOptimize\Core\Registry;
use JchOptimize\Core\Uri\Utils;

\defined('_JCH_EXEC') or exit('Restricted access');
class Cdn extends \JchOptimize\Core\Html\Callbacks\AbstractCallback
{
    protected string $context = 'default';
    protected ?UriInterface $baseUri = null;
    protected string $searchRegex = '';
    protected string $localhost = '';

    private CdnCore $cdn;

    public function __construct(Container $container, Registry $params, CdnCore $cdn)
    {
        parent::__construct($container, $params);
        $this->cdn = $cdn;
    }

    public function processMatches(array $matches): string
    {
        if ('' === \trim($matches[0])) {
            return $matches[0];
        }

        try {
            $element = HtmlElementBuilder::load($matches[0]);
        } catch (PregErrorException $e) {
            return $matches[0];
        }
        \assert($element instanceof BaseElement);
        if ($element instanceof Style) {
            $content = $element->getChildren()[0];
            if (!empty($content)) {
                $element->replaceChild(0, $this->loadCdnInCssStyle($content));
            }
        }
        if ($element->hasAttribute('style')) {
            $style = $element->getStyle();
            if (!empty($style)) {
                $element->style($this->loadCdnInCssStyle($style));
            }
        }
        if ($element->hasAttribute('srcset')) {
            $element->attribute('srcset', $this->handleSrcSetValues($element->attributeValue('srcset')));
        }
        if ($element->hasAttribute('data-srcset')) {
            $element->attribute('data-srcset', $this->handleSrcSetValues($element->attributeValue('data-srcset')));
        }
        if ($element->hasAttribute('src')) {
            $element->attribute('src', $this->srcValueToCdnValue($element->attributeValue('src')));
        }
        if ($element->hasAttribute('data-src')) {
            $element->attribute('data-src', (string) $this->srcValueToCdnValue(Utils::uriFor($element->attributeValue('data-src'))));
        }
        if ($element->hasAttribute('href')) {
            $element->attribute('href', $this->srcValueToCdnValue($element->attributeValue('href')));
        }
        if ($element instanceof Video && $element->hasAttribute('poster')) {
            $element->attribute('poster', $this->srcValueToCdnValue($element->attributeValue('poster')));
        }
        if ($element->hasAttribute('content')) {
            $element->attribute('content', $this->cdnInContentAttributes($element->attributeValue('content')));
        }

        return $element->render();
    }

    public function setBaseUri(UriInterface $baseUri): void
    {
        $this->baseUri = $baseUri;
    }

    public function setLocalhost(string $sLocalhost): void
    {
        $this->localhost = $sLocalhost;
    }

    public function setContext(string $sContext): void
    {
        $this->context = $sContext;
    }

    public function setSearchRegex(string $sSearchRegex): void
    {
        $this->searchRegex = $sSearchRegex;
    }

    protected function loadCdnInCssStyle(string $css): string
    {
        \preg_match_all('#url\\([\'"]?('.$this->searchRegex.CssParser::cssUrlValueToken().')([\'"]?\\))#i', $css, $matches, \PREG_SET_ORDER);
        $matches = \array_unique($matches, \SORT_REGULAR);
        foreach ($matches as $match) {
            if (!empty($match[1])) {
                $cdnUri = $this->cdn->loadCdnResource($this->resolvePathToBase(Utils::uriFor($match[1])));
                $css = \str_replace($match[1], (string) $cdnUri, $css);
            }
        }

        return $css;
    }

    protected function srcValueToCdnValue(UriInterface $uri): UriInterface
    {
        $resolvedSrcValue = $this->resolvePathToBase($uri);

        return $this->cdn->loadCdnResource($resolvedSrcValue, $uri);
    }

    protected function resolvePathToBase(UriInterface $uri): UriInterface
    {
        return UriResolver::resolve($this->getBaseUri(), $uri);
    }

    protected function handleSrcSetValues(string $srcset): string
    {
        $regex = '(?:^|,)\\s*+('.$this->searchRegex.'([^,]++))';
        \preg_match_all('#'.$regex.'#i', $srcset, $aUrls, \PREG_SET_ORDER);
        // Cache urls in the srcset as we process them to ensure we don't process the same url twice
        $processedUrls = [];
        foreach ($aUrls as $aUrlMatch) {
            $uri = Utils::uriFor($aUrlMatch[2]);
            if (!empty($aUrlMatch[0]) && !\in_array((string) $uri, $processedUrls)) {
                $processedUrls[] = $uri;
                $resolvedUri = $this->resolvePathToBase($uri);
                $cdnUrl = $this->cdn->loadCdnResource($resolvedUri, $uri);
                $srcset = \str_replace($aUrlMatch[2], (string) $cdnUrl, $srcset);
            }
        }

        return $srcset;
    }

    protected function cdnInContentAttributes(string $value): string
    {
        \preg_match_all('#'.$this->searchRegex.'#i', $value, $matches, \PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!empty($match[1])) {
                $uri = $this->cdn->loadCdnResource($this->resolvePathToBase(Utils::uriFor($match[1])));
                $value = \str_replace($match[1], (string) $uri, $value);
            }
        }

        return $value;
    }

    protected function getBaseUri(): UriInterface
    {
        if ($this->baseUri instanceof UriInterface) {
            return $this->baseUri;
        }

        throw new PropertyNotFoundException('Base URI not set in '.\get_class($this));
    }
}
