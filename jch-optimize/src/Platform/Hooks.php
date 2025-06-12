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

namespace JchOptimize\WordPress\Platform;

use JchOptimize\Core\Platform\HooksInterface;
use SplObjectStorage;

use function apply_filters;

class Hooks implements HooksInterface
{
    /**
     * @inheritDoc
     */
    public function onPageCacheSetCaching(): bool
    {
        return apply_filters('jch_optimize_page_cache_set_caching', true);
    }

    /**
     * @inheritDoc
     */
    public function onPageCacheGetKey(array $parts): array
    {
        return apply_filters('jch_optimize_get_page_cache_id', $parts);
    }

    public function onUserPostForm(): void
    {
        // TODO: Implement onUserPostForm() method.
    }

    public function onUserPostFormDeleteCookie(): void
    {
        // TODO: Implement onUserPostFormDeleteCookie() method.
    }

    /**
     * @inheritDoc
     */
    public function onHttp2GetPreloads(SplObjectStorage $preloads): mixed
    {
        return apply_filters('jch_optimize_get_http2_preloads', $preloads);
    }
}
