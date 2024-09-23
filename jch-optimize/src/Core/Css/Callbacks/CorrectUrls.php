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

namespace JchOptimize\Core\Css\Callbacks;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use JchOptimize\Core\Cdn;
use JchOptimize\Core\Css\Parser;
use JchOptimize\Core\FeatureHelpers\LazyLoadExtended;
use JchOptimize\Core\FeatureHelpers\Webp;
use JchOptimize\Core\Http2Preload;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\UriComparator;
use JchOptimize\Core\Uri\Utils;
use Joomla\DI\Container;
use Joomla\Registry\Registry;

use function defined;
use function in_array;
use function preg_replace_callback;
use function str_replace;

defined('_JCH_EXEC') or die('Restricted access');

class CorrectUrls extends AbstractCallback
{
    /** @var bool True if this callback is called when preloading assets for HTTP/2 */
    public bool $isHttp2 = false;
    /** @var bool If Optimize CSS Delivery is disabled, only fonts are preloaded */
    public bool $isFontsOnly = false;
    /** @var bool If run from admin we populate the $images array */
    public bool $isBackend = false;
    /** @var Cdn */
    public Cdn $cdn;
    /** @var Http2Preload */
    public Http2Preload $http2Preload;
    /** @var array */
    private array $images = [];
    /** @var array An array of external domains that we'll add preconnects for */
    private array $preconnects = [];
    /** @var array */
    private array $cssInfos;

    public array $cssBgImagesSelectors = [];

    public function __construct(Container $container, Registry $params, Cdn $cdn, Http2Preload $http2Preload)
    {
        parent::__construct($container, $params);

        $this->cdn = $cdn;
        $this->http2Preload = $http2Preload;
    }

    /**
     * @inheritDoc
     */
    public function processMatches(array $matches, string $context): string
    {
        $sRegex = '(?>u?[^u]*+)*?\K(?:' . Parser::cssUrlWithCaptureValueToken(true) . '|$)';

        if ($context == 'import') {
            $sRegex = Parser::cssAtImportWithCaptureValueToken(true);
        }

        $css = preg_replace_callback('#' . $sRegex . '#i', function ($aInnerMatches) use ($context) {
            return $this->processInnerMatches($aInnerMatches, $context);
        }, $matches[0]);

        //Lazy-load background images
        if (JCH_PRO && $this->params->get('lazyload_enable', '0')
            && $this->params->get('pro_lazyload_bgimages', '0')
            && !in_array($context, ['font-face', 'import'])) {
            /** @see LazyLoadExtended::handleCssBgImages() */
            return $this->getContainer()->get(LazyLoadExtended::class)->handleCssBgImages($this, $css);
        }

        return $css;
    }

    /**
     * @param string[] $matches
     *
     * @psalm-param array<string> $matches
     */
    protected function processInnerMatches(array $matches, $context)
    {
        if (empty($matches[0])) {
            return $matches[0];
        }

        $originalUri = Utils::uriFor($matches[1]);

        if ($originalUri->getScheme() !== 'data' && $originalUri->getPath() != '' && $originalUri->getPath() != '/') {
            //The urls were already corrected on a previous run, we're only preloading assets in critical CSS and return
            if ($this->isHttp2) {
                $sFileType = $context == 'font-face' ? 'font' : 'image';

                //If Optimize CSS Delivery not enabled, we'll only preload fonts.
                if ($this->isFontsOnly && $sFileType != 'font') {
                    return false;
                }

                $this->http2Preload->add($originalUri, $sFileType);

                return true;
            }
            //Get the url of the file that contained the CSS
            $cssFileUri = empty($this->cssInfos['url']) ? new Uri() : $this->cssInfos['url'];
            $cssFileUri = UriResolver::resolve(SystemUri::currentUri(), $cssFileUri);
            $imageUri = UriResolver::resolve($cssFileUri, $originalUri);

            if (!UriComparator::isCrossOrigin($imageUri)) {
                $imageUri = $this->cdn->loadCdnResource($imageUri);
            } elseif ($this->params->get('pro_optimizeFonts_enable', '0')) {
                //Cache external domains to add preconnects for them
                $domain = Uri::composeComponents($imageUri->getScheme(), $imageUri->getAuthority(), '', '', '');

                if (!in_array($domain, $this->preconnects)) {
                    $this->preconnects[] = $domain;
                }
            }

            if ($this->isBackend && $context != 'font-face') {
                $this->images[] = $imageUri;
            }

            if (JCH_PRO && $this->params->get('pro_load_webp_images', '0')) {
                /** @see Webp::getWebpImages() */
                $imageUri = $this->getContainer()->get(Webp::class)->getWebpImages($imageUri) ?? $imageUri;
            }

            // If URL without quotes and contains any parentheses, whitespace characters,
            // single quotes (') and double quotes (") that are part of the URL, quote URL
            if (strpos($matches[0], 'url(' . $originalUri . ')') !== false && preg_match(
                '#[()\s\'"]#',
                $imageUri
            )) {
                $imageUri = '"' . $imageUri . '"';
            }

            return str_replace($matches[1], $imageUri, $matches[0]);
        } else {
            return $matches[0];
        }
    }

    public function setCssInfos($cssInfos): void
    {
        $this->cssInfos = $cssInfos;
    }

    public function getImages(): array
    {
        return $this->images;
    }

    public function getPreconnects(): array
    {
        return $this->preconnects;
    }

    public function getCssBgImagesSelectors(): array
    {
        return $this->cssBgImagesSelectors;
    }
}
