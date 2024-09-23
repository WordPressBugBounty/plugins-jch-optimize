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

use _JchOptimizeVendor\GuzzleHttp\Psr7\UriComparator as GuzzleComparator;
use _JchOptimizeVendor\Psr\Http\Message\UriInterface;

final class UriComparator
{
    public static function isCrossOrigin(UriInterface $modified): bool
    {
        foreach (\JchOptimize\Core\Uri\Utils::originDomains() as $originDomain) {
            if (!GuzzleComparator::isCrossOrigin($originDomain, $modified)) {
                return \false;
            }
        }

        return \true;
    }
}
