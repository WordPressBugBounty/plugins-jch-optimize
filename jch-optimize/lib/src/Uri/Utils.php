<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads.
 *
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Uri;

use _JchOptimizeVendor\GuzzleHttp\Psr7\Uri;
use _JchOptimizeVendor\GuzzleHttp\Psr7\UriResolver;
use _JchOptimizeVendor\GuzzleHttp\Psr7\Utils as GuzzleUtils;
use _JchOptimizeVendor\Psr\Http\Message\UriInterface;
use InvalidArgumentException;
use JchOptimize\ContainerFactory;
use JchOptimize\Core\Cdn;
use JchOptimize\Core\SystemUri;

class Utils
{
    public static function originDomains(): array
    {
        $container = ContainerFactory::getContainer();

        /** @var Cdn $cdn */
        $cdn = $container->get(Cdn::class);
        $domains = $cdn->getCdnDomains();
        $cdnDomains = \array_column($domains, 'domain');
        $systemDomain = new Uri(SystemUri::currentBaseFull());
        $originDomains = [$systemDomain];
        // We count each configured CDN domain as 'equivalent' to the system domain, so we just
        // build an array by swapping the CDN domains
        foreach ($cdnDomains as $cdnDomain) {
            $originDomains[] = UriResolver::resolve($systemDomain, $cdnDomain)->withPath($systemDomain->getPath());
        }

        return $originDomains;
    }

    /**
     * Returns a UriInterface for an accepted value. If there's an error processing the
     * received value, an '_invalidUri' string is returned,
     * Use this whenever possible as Windows paths are converted to unix style so Uris can be created.
     */
    public static function uriFor(UriInterface|string $uri): UriInterface
    {
        // convert Window directory to unix style
        if (\is_string($uri)) {
            $uri = \strtr(\trim($uri), '\\', '/');
        }

        try {
            return \JchOptimize\Core\Uri\UriNormalizer::normalize(GuzzleUtils::uriFor($uri));
        } catch (InvalidArgumentException) {
            return new Uri('_invalidUri');
        }
    }
}
