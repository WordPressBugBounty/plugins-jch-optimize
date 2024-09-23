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


use JchOptimize\Core\Registry;

use function get_current_blog_id;
use function header;
use function is_multisite;

class Cache implements \JchOptimize\Core\Interfaces\Cache
{
    public static function cleanThirdPartyPageCache(): void
    {
        // Not currently used on this platform.
    }

    public static function prepareDataFromCache(?array $data): ?array
    {
        return $data;
    }

    public static function outputData(array $data): void
    {
        /** @psalm-var array{headers:string[], body:string} $data */
        if (!empty($data['headers'])) {
            foreach ($data['headers'] as $header) {
                header($header);
            }
        }

        echo $data['body'];

        exit();
    }

    /**
     * @param   Registry  $params
     *
     * @return string
     */
    public static function getCacheStorage(Registry $params): string
    {
        /** @var string */
        return $params->get('pro_cache_storage_adapter', 'filesystem');
    }


    public static function isPageCacheEnabled(Registry $params, bool $nativeCache = false): bool
    {
        return (bool)$params->get('cache_enable', '0');
    }

    /**
     * @param   bool  $pageCache
     *
     * @return string
     * @deprecated
     */
    public static function getCacheNamespace(bool $pageCache = false): string
    {
        $id = '';

        if (is_multisite()) {
            $id = get_current_blog_id();
        }

        if ($pageCache) {
            return 'jchoptimizepagecache' . $id;
        }

        return 'jchoptimizecache' . $id;
    }

    public static function isCaptureCacheIncompatible(): bool
    {
        return is_multisite();
    }

    public static function getPageCacheNamespace(): string
    {
        return 'jchoptimizepagecache' . self::getCurrentSiteId();
    }

    public static function getGlobalCacheNamespace(): string
    {
        return 'jchoptimizecache' . self::getCurrentSiteId();
    }

    public static function getTaggableCacheNamespace(): string
    {
        return 'jchoptimizetags' . self::getCurrentSiteId();
    }

    private static function getCurrentSiteId(): string
    {
        if (is_multisite()) {
            return (string) get_current_blog_id();
        }

        return '';
    }
}
