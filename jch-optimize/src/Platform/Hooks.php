<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Platform;

use function apply_filters;

class Hooks implements \JchOptimize\Core\Interfaces\Hooks
{

    /**
     * @inheritDoc
     */
    public static function onPageCacheSetCaching(): bool
    {
        return apply_filters('jch_optimize_page_cache_set_caching', true);
    }

    /**
     * @inheritDoc
     */
    public static function onPageCacheGetKey(array $parts): array
    {
        return apply_filters('jch_optimize_get_page_cache_id', $parts);
    }

    public static function onUserPostForm(): void
    {
        // TODO: Implement onUserPostForm() method.
    }

    public static function onUserPostFormDeleteCookie(): void
    {
        // TODO: Implement onUserPostFormDeleteCookie() method.
    }

    /**
     * @inheritDoc
     */
    public static function onHttp2GetPreloads(array $preloads): array
    {
        return apply_filters('jch_optimize_get_http2_preloads', $preloads);
    }
}