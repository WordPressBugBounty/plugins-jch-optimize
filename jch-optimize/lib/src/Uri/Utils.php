<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Uri;

use _JchOptimizeVendor\V91\GuzzleHttp\Psr7\UriResolver;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use InvalidArgumentException;
use JchOptimize\Core\Cdn\Cdn;
use JchOptimize\Core\Cdn\CdnDomain;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\SystemUri;

use function is_string;
use function pathinfo;
use function strtr;

use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

class Utils
{
    public static function originDomains(PathsInterface $paths, Cdn $cdn): array
    {
        $cdnDomains = $cdn->getCdnDomains();

        $systemDomain = new Uri(SystemUri::siteBaseFull($paths));
        $originDomains = [$systemDomain];
        //We count each configured CDN domain as 'equivalent' to the system domain, so we just
        //build an array by swapping the CDN domains
        /** @var CdnDomain $cdnDomain */
        foreach ($cdnDomains as $cdnDomain) {
            $originDomains[] = UriResolver::resolve(
                $systemDomain,
                $cdnDomain->getUri()
            )->withPath($systemDomain->getPath());
        }

        return $originDomains;
    }

    /**
     * Returns a UriInterface for an accepted value. If there's an error processing the
     * received value, an '_invalidUri' string is returned,
     * Use this whenever possible as Windows paths are converted to unix style so Uris can be created
     *
     * @param string|UriInterface $uri
     * @param bool $encodeUrl
     * @return Uri
     */
    public static function uriFor(UriInterface|string $uri, bool $encodeUrl = false): UriInterface
    {
        //convert Window directory to unix style
        if (is_string($uri)) {
            $uri = strtr(trim($uri), '\\', '/');
        }

        try {
            return new Uri((string)$uri, $encodeUrl);
        } catch (InvalidArgumentException) {
            return new Uri('_invalidUri');
        }
    }

    public static function fileExtension(UriInterface $uri): string
    {
        return pathinfo($uri->getPath(), PATHINFO_EXTENSION);
    }

    public static function filename(UriInterface $uri): string
    {
        return pathinfo($uri->getPath(), PATHINFO_FILENAME);
    }
}
