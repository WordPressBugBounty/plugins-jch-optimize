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

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use JchOptimize\Core\Cdn as CdnCore;
use JchOptimize\Core\Css\Parser as CssParser;
use JchOptimize\Core\Uri\UriNormalizer;
use JchOptimize\Core\Uri\Utils;
use Joomla\DI\Container;
use Joomla\Registry\Registry;
use Psr\Http\Message\UriInterface;

use function array_unique;
use function defined;
use function in_array;
use function preg_match_all;
use function str_replace;

defined('_JCH_EXEC') or die('Restricted access');

class Cdn extends AbstractCallback
{
    protected string $context = 'default';
    protected UriInterface $baseUri;
    protected string $searchRegex = '';
    protected string $localhost = '';
    /**
     * @var CdnCore
     */
    private CdnCore $cdn;

    public function __construct(Container $container, Registry $params, CdnCore $cdn)
    {
        parent::__construct($container, $params);

        $this->cdn = $cdn;
    }

    /**
     * @inheritDoc
     */
    public function processMatches(array $matches): string
    {
        if (trim($matches[0]) === '') {
            return $matches[0];
        }


        switch ($this->context) {
            case ('cssurl'):
                //This would be either a <style> element, or an HTML element with a style attribute, containing one or more CSS urls
                $styleOrElement = $matches[0];
                $regex = 'url\([\'"]?(' . $this->searchRegex . CssParser::cssUrlValueToken() . ')([\'"]?\))';
                //Find all css urls in content
                preg_match_all('#' . $regex . '#i', $styleOrElement, $aCssUrls, PREG_SET_ORDER);

                //Prevent modifying the same url multiple times
                $aCssUrls = array_unique($aCssUrls, SORT_REGULAR);

                foreach ($aCssUrls as $aCssUrlMatch) {
                    $cssUrl = @$aCssUrlMatch[0] ?: false;
                    $urlWithQuery = @$aCssUrlMatch[1] ?: false;
                    $url = @$aCssUrlMatch[2];

                    if ($cssUrl !== false && $url !== false) {
                        $uri = Utils::uriFor($url);
                        $resolvedUri = $this->resolvePathToBase($uri);
                        $cdnUrl = $this->cdn->loadCdnResource($resolvedUri, $uri);

                        //First replace the url in the css url
                        $cdnCssUrl = str_replace($urlWithQuery, (string)$cdnUrl, $cssUrl);
                        //Replace the css url in content
                        $styleOrElement = str_replace($cssUrl, $cdnCssUrl, $styleOrElement);
                    }
                }

                return $styleOrElement;

            case('srcset'):

                $fullMatch = $matches[0];
                $srcSetAttr = @$matches[2] ?: false;
                $srcSetValue = @$matches[4] ?: false;
                $dataSrcSetAttr = @$matches[5] ?: @$matches[8] ?: false;
                $dataSrcSetValue = @$matches[7] ?: @$matches[10] ?: false;
                $returnMatch = $fullMatch;

                if ($srcSetAttr !== false && $srcSetValue !== false) {
                    $returnMatch = $this->handleSrcSetValues($srcSetAttr, $srcSetValue, $returnMatch);
                }

                if ($dataSrcSetAttr !== false && $dataSrcSetValue !== false) {
                    $returnMatch = $this->handleSrcSetValues($dataSrcSetAttr, $dataSrcSetValue, $returnMatch);
                }

                return $returnMatch;

            default:

                $fullMatch = $matches[0];
                $hrefSrcAttr = @$matches[3] ?: false;
                $hrefSrcValue = @$matches[5] ?: false;
                $hrefSrcValueWithQuery = @$matches[6] ?: false;
                $dataSrcAttr = @$matches[7] ?: @$matches[11] ?: false;
                $dataSrcValue = @$matches[9] ?: @$matches[13] ?: false;
                $dataSrcValueWithQuery = @$matches[10] ?: @$matches[14] ?: false;
                $returnMatch = $fullMatch;

                if ($hrefSrcAttr !== false && $hrefSrcValue !== false) {
                    $returnMatch = $this->srcValueToCdnValue(
                        $hrefSrcValue,
                        $hrefSrcValueWithQuery,
                        $hrefSrcAttr,
                        $returnMatch
                    );
                }

                if ($dataSrcAttr !== false && $dataSrcValue !== false) {
                    $returnMatch = $this->srcValueToCdnValue(
                        $dataSrcValue,
                        $dataSrcValueWithQuery,
                        $dataSrcAttr,
                        $returnMatch
                    );
                }

                return $returnMatch;
        }
    }

    protected function srcValueToCdnValue(string $srcValue, string $srcValueWithQuery, string $srcAttr, string $returnMatch): string
    {
        $srcUri = Utils::uriFor($srcValue);
        $resolvedSrcValue = $this->resolvePathToBase($srcUri);
        $cdnSrcValue = $this->cdn->loadCdnResource($resolvedSrcValue, $srcUri);

        //First replace the url in the data-src attribute
        $cdnDataSrcAttr = str_replace($srcValueWithQuery, (string)$cdnSrcValue, $srcAttr);

        //Then replace the original attribute with the attribute containing CDN url
        return str_replace($srcAttr, $cdnDataSrcAttr, $returnMatch);
    }

    protected function resolvePathToBase(UriInterface $uri): UriInterface
    {
        return UriResolver::resolve($this->baseUri, $uri);
    }

    protected function handleSrcSetValues(string $attribute, $uri, string $returnMatch): string
    {
        $cdnSrcSetAttr = $attribute;
        $regex = '(?:^|,)\s*+(' . $this->searchRegex . '([^,]++))';
        preg_match_all('#' . $regex . '#i', $uri, $aUrls, PREG_SET_ORDER);
        //Cache urls in the srcset as we process them to ensure we don't process the same url twice
        $processedUrls = [];

        foreach ($aUrls as $aUrlMatch) {
            $uri = Utils::uriFor($aUrlMatch[2]);

            if (!empty($aUrlMatch[0]) && !in_array((string)$uri, $processedUrls)) {
                $processedUrls[] = $uri;
                $resolvedUri = $this->resolvePathToBase($uri);
                $cdnUrl = $this->cdn->loadCdnResource($resolvedUri, $uri);
                $cdnSrcSetAttr = str_replace($aUrlMatch[2], (string)$cdnUrl, $cdnSrcSetAttr);
            }
        }

        $returnMatch = str_replace($attribute, $cdnSrcSetAttr, $returnMatch);

        return $returnMatch;
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
}
