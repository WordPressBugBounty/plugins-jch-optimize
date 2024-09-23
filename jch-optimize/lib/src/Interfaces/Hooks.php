<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads.
 *
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Interfaces;

\defined('_JCH_EXEC') or exit('Restricted access');
interface Hooks
{
    /**
     * Set Page Caching enabled or disabled.
     */
    public static function onPageCacheSetCaching(): bool;

    /**
     * Add an item to a given array that will be used in generating the key for page cache.
     *
     * @param array<array-key, mixed> $parts
     *
     * @return array<array-key, mixed>
     */
    public static function onPageCacheGetKey(array $parts): array;

    /**
     * Set a cookie when a user posts a form to prevent caching for user.
     */
    public static function onUserPostForm(): void;

    /**
     * Deletes the user_posted_form cookie if the setting is disabled.
     */
    public static function onUserPostFormDeleteCookie(): void;

    /**
     * Allows filtering of the HTTP2 $preloads array.
     *
     * @param array $preloads Multidimensional array of files for preloads
     */
    public static function onHttp2GetPreloads(array $preloads): array;
}
