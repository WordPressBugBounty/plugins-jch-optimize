<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Html\Callbacks;

use _JchOptimizeVendor\V91\GuzzleHttp\Psr7\UriResolver;
use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use JchOptimize\Core\Cdn\Cdn as CdnCore;
use JchOptimize\Core\Css\Components\CssUrl;
use JchOptimize\Core\Css\Parser as CssParser;
use JchOptimize\Core\Exception\InvalidArgumentException;
use JchOptimize\Core\Exception\PropertyNotFoundException;
use JchOptimize\Core\Html\Elements\Style;
use JchOptimize\Core\Html\Elements\Video;
use JchOptimize\Core\Html\HtmlElementInterface;
use JchOptimize\Core\Registry;
use JchOptimize\Core\Uri\Utils;

use function defined;
use function get_class;
use function implode;
use function in_array;
use function preg_match_all;
use function preg_replace_callback;
use function str_replace;

use const PREG_SET_ORDER;

defined('_JCH_EXEC') or die('Restricted access');

class Cdn extends AbstractCallback
{
    protected string $context = 'default';
    protected ?UriInterface $baseUri = null;
    protected string $searchRegex = '';
    protected string $localhost = '';
    private string $fileExtRegex;

    public function __construct(Container $container, Registry $params, private CdnCore $cdn)
    {
        parent::__construct($container, $params);

        $this->fileExtRegex = implode('|', $cdn->getCdnFileTypes());
    }

    protected function internalProcessMatches(HtmlElementInterface $element): string
    {
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
            $element->attribute(
                'data-src',
                (string)$this->srcValueToCdnValue(
                    Utils::uriFor($element->attributeValue('data-src'))
                )
            );
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

    protected function loadCdnInCssStyle(string $css): string
    {
        $urlRegex = CssParser::cssUrlToken();

        return preg_replace_callback(
            "#{$urlRegex}#i",
            function ($match) {
                try {
                    $cssUrl = CssUrl::load($match[0]);
                } catch (InvalidArgumentException) {
                    return $match[0];
                }

                $cdnUri = $this->cdn->loadCdnResource($this->resolvePathToBase($cssUrl->getUri()));
                return $cssUrl->setUri($cdnUri)->render();
            },
            $css
        );
    }

    protected function srcValueToCdnValue(UriInterface $uri): UriInterface
    {
        if (!$this->validateFileExt($uri)) {
            return $uri;
        }

        $resolvedSrcValue = $this->resolvePathToBase($uri);

        return $this->cdn->loadCdnResource($resolvedSrcValue, $uri);
    }

    protected function resolvePathToBase(UriInterface $uri): UriInterface
    {
        return UriResolver::resolve($this->getBaseUri(), $uri);
    }

    protected function handleSrcSetValues(string $srcset): string
    {
        $regex = '(?:^|,)\s*+(' . $this->searchRegex . '([^,]++))';
        preg_match_all('#' . $regex . '#i', $srcset, $aUrls, PREG_SET_ORDER);
        //Cache urls in the srcset as we process them to ensure we don't process the same url twice
        $processedUrls = [];

        foreach ($aUrls as $aUrlMatch) {
            $uri = Utils::uriFor($aUrlMatch[2]);

            if (!empty($aUrlMatch[0]) && !in_array((string)$uri, $processedUrls)) {
                $processedUrls[] = $uri;
                $resolvedUri = $this->resolvePathToBase($uri);
                $cdnUrl = (string)$this->cdn->loadCdnResource($resolvedUri, $uri);
                $srcset = str_replace($aUrlMatch[2], $cdnUrl, $srcset);
            }
        }

        return $srcset;
    }

    protected function cdnInContentAttributes(string $value): string
    {
        preg_match_all('#' . $this->searchRegex . '#i', $value, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (!empty($match[1])) {
                $uri = $this->cdn->loadCdnResource($this->resolvePathToBase(Utils::uriFor($match[1])));
                $value = str_replace($match[1], (string)$uri, $value);
            }
        }

        return $value;
    }

    public function setBaseUri(UriInterface $baseUri): void
    {
        $this->baseUri = $baseUri;
    }

    protected function getBaseUri(): UriInterface
    {
        if ($this->baseUri instanceof UriInterface) {
            return $this->baseUri;
        }

        throw new PropertyNotFoundException('Base URI not set in ' . get_class($this));
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

    public function validateFileExt(UriInterface $uri): bool
    {
        $fileExt = Utils::fileExtension($uri);

        if (empty($fileExt)) {
            return false;
        }

        return (bool)preg_match("#{$this->fileExtRegex}#i", $fileExt);
    }
}
