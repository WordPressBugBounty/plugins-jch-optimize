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

use JchOptimize\Core\Platform\CacheInterface;
use JchOptimize\Core\Registry;

use function get_current_blog_id;
use function header;
use function is_multisite;

class Cache implements CacheInterface
{
    public function cleanThirdPartyPageCache(): void
    {
        // Not currently used on this platform.
    }

    public function prepareDataFromCache(?array $data): ?array
    {
        return $data;
    }

    #[NoReturn]
    public function outputData(array $data): void
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


    public function isPageCacheEnabled(Registry $params, bool $nativeCache = false): bool
    {
        return (bool)$params->get('cache_enable', '0');
    }

    /**
     * @param   bool  $pageCache
     *
     * @return string
     * @deprecated
     */
    public function getCacheNamespace(bool $pageCache = false): string
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

    public function isCaptureCacheIncompatible(): bool
    {
        return is_multisite();
    }

    public function getPageCacheNamespace(): string
    {
        return 'jchoptimizepagecache' . $this->getCurrentSiteId();
    }

    public function getGlobalCacheNamespace(): string
    {
        return 'jchoptimizecache' . $this->getCurrentSiteId();
    }

    public function getTaggableCacheNamespace(): string
    {
        return 'jchoptimizetags' . $this->getCurrentSiteId();
    }

    private function getCurrentSiteId(): string
    {
        if (is_multisite()) {
            return (string) get_current_blog_id();
        }

        return '';
    }
}
