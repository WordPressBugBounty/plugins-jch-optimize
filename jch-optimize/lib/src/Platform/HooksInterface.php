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

namespace JchOptimize\Core\Platform;

use SplObjectStorage;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

interface HooksInterface
{
    /**
     * Set Page Caching enabled or disabled
     *
     * @return bool
     */
    public function onPageCacheSetCaching(): bool;

    /**
     * Add an item to a given array that will be used in generating the key for page cache
     *
     * @param array<array-key, mixed> $parts
     * @return array<array-key, mixed>
     */
    public function onPageCacheGetKey(array $parts): array;

    /**
     * Set a cookie when a user posts a form to prevent caching for user
     *
     * @return void
     */
    public function onUserPostForm(): void;

    /**
     * Deletes the user_posted_form cookie if the setting is disabled
     *
     * @return void
     */
    public function onUserPostFormDeleteCookie(): void;

    /**
     * Allows filtering of the HTTP2 $preloads array
     *
     * @param  SplObjectStorage  $preloads  Storage of Preload objects
     *
     * @return mixed
     */
    public function onHttp2GetPreloads(SplObjectStorage $preloads): mixed;
}
