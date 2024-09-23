<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads.
 *
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Uri;

use _JchOptimizeVendor\GuzzleHttp\Psr7\UriNormalizer as GuzzleNormalizer;
use _JchOptimizeVendor\Psr\Http\Message\UriInterface;

class UriNormalizer
{
    public static function normalize(UriInterface $uri): UriInterface
    {
        return GuzzleNormalizer::normalize($uri, GuzzleNormalizer::CAPITALIZE_PERCENT_ENCODING | GuzzleNormalizer::DECODE_UNRESERVED_CHARACTERS | GuzzleNormalizer::REMOVE_DOT_SEGMENTS | GuzzleNormalizer::REMOVE_DUPLICATE_SLASHES);
    }

    public static function pageCacheIdNormalize(UriInterface $uri): UriInterface
    {
        return GuzzleNormalizer::normalize($uri->withPath(\rtrim($uri->getPath(), '/\\')), GuzzleNormalizer::PRESERVING_NORMALIZATIONS | GuzzleNormalizer::REMOVE_DUPLICATE_SLASHES | GuzzleNormalizer::SORT_QUERY_PARAMETERS);
    }

    public static function systemUriNormalize(UriInterface $uri): UriInterface
    {
        return GuzzleNormalizer::normalize($uri, GuzzleNormalizer::CAPITALIZE_PERCENT_ENCODING | GuzzleNormalizer::DECODE_UNRESERVED_CHARACTERS | GuzzleNormalizer::CONVERT_EMPTY_PATH | GuzzleNormalizer::REMOVE_DEFAULT_HOST | GuzzleNormalizer::REMOVE_DEFAULT_PORT | GuzzleNormalizer::REMOVE_DOT_SEGMENTS | GuzzleNormalizer::REMOVE_DUPLICATE_SLASHES);
    }
}
