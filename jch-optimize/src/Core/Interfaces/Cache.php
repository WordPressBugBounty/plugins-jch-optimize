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

namespace JchOptimize\Core\Interfaces;

use Joomla\Registry\Registry;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

interface Cache
{
    public static function cleanThirdPartyPageCache(): void;

    public static function prepareDataFromCache(?array $data): ?array;

    public static function outputData(array $data): void;

    public static function isPageCacheEnabled(Registry $params, bool $nativeCache = false): bool;

    /**
     * @deprecated
     */
    public static function getCacheNamespace(bool $pageCache = false): string;

    public static function getPageCacheNamespace(): string;

    public static function getGlobalCacheNamespace(): string;

    public static function getTaggableCacheNamespace(): string;

    public static function isCaptureCacheIncompatible(): bool;
}
