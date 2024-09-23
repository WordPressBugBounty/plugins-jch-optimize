<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Uri;

use Psr\Http\Message\UriInterface;
use GuzzleHttp\Psr7\UriComparator as GuzzleComparator;

final class UriComparator
{
    public static function isCrossOrigin(UriInterface $modified): bool
    {
        foreach (Utils::originDomains() as $originDomain){
            if(!GuzzleComparator::isCrossOrigin($originDomain, $modified)){
                return false;
            }
        }

        return true;
    }
}
