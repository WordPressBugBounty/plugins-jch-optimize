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

use JchOptimize\Core\Registry;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

interface CacheInterface
{
    public function cleanThirdPartyPageCache(): void;

    public function prepareDataFromCache(?array $data): ?array;

    public function outputData(array $data): void;

    public function isPageCacheEnabled(Registry $params, bool $nativeCache = false): bool;

    /**
     * @deprecated
     */
    public function getCacheNamespace(bool $pageCache = false): string;

    public function getPageCacheNamespace(): string;

    public function getGlobalCacheNamespace(): string;

    public function getTaggableCacheNamespace(): string;

    public function isCaptureCacheIncompatible(): bool;
}
