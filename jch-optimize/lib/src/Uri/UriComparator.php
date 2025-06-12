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

use _JchOptimizeVendor\V91\GuzzleHttp\Psr7\UriComparator as GuzzleComparator;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use JchOptimize\Core\Platform\PathsInterface;
use _JchOptimizeVendor\V91\GuzzleHttp\Psr7\UriResolver;
use JchOptimize\Core\Cdn\Cdn;
use JchOptimize\Core\SystemUri;

final class UriComparator
{
    public static function isCrossOrigin(UriInterface $modified): bool
    {
        $systemDomain = new Uri(SystemUri::currentBaseFull());
        $modified = UriResolver::resolve($systemDomain, $modified);

        return  GuzzleComparator::isCrossOrigin($systemDomain, $modified);
    }

    public static function existsLocally(UriInterface $uri, Cdn $cdn, PathsInterface $paths): bool
    {
        foreach (Utils::originDomains($paths, $cdn) as $originDomain) {
            $systemDomain = new Uri(SystemUri::currentBaseFull());
            $modified = UriResolver::resolve($systemDomain, $uri);

            if (!GuzzleComparator::isCrossOrigin($originDomain, $modified)) {
                return true;
            }
        }

        return false;
    }
}
