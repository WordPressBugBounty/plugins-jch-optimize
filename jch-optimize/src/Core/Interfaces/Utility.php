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
use stdClass;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

interface Utility
{
    /**
     *
     * @param string $text
     */
    public static function translate(string $text): string;

    /**
     * Returns true if current user is not logged in
     *
     * @return bool
     */
    public static function isGuest(): bool;

    public static function sendHeaders(array $headers): void;

    /**
     * Returns array of response headers that are set or already sent
     */
    public static function getHeaders(): array;

    /**
     *
     * @param string $userAgent
     */
    public static function userAgent(string $userAgent): stdClass;

    /**
     * Indicates if current client is mobile
     *
     * @return bool
     */
    public static function isMobile(): bool;

    /**
     * Indicates if page cache is enabled. If nativeCache is true then we're specifically checking the
     * jchoptimize page cache
     *
     * @param Registry $params
     * @param bool     $nativeCache
     *
     * @return bool
     * @deprecated Use Cache::isPageCacheEnabled() instead
     */
    public static function isPageCacheEnabled(Registry $params, bool $nativeCache = false): bool;

    /**
     * Should return one of the following based on the current configuration
     * filesystem, memcached, apcu, redis, wincache
     *
     * @param Registry $params
     *
     * @return string
     * @deprecated Use Cache::getCacheStorage() instead
     */
    public static function getCacheStorage(Registry $params): string;

    /**
     * Should return the attribute used to store content values for popover that the version of Bootstrap
     * is using
     *
     * @return string
     */
    public static function bsTooltipContentAttribute(): string;

    /**
     * @param string $message
     * @param string $messageType
     */
    public static function publishAdminMessages(string $message, string $messageType);

    /**
     * Determines if the site is currently configured to compress the HTML using gzip
     *
     * @return bool
     */
    public static function isSiteGzipEnabled(): bool;

    /**
     * We may need to do some manipulation of the data retrieved from Page cache depending on the platform
     *
     * @param array|null $data
     *
     * @return array
     * @deprecated Use Cache::prepareDataFromCache() instead
     */
    public static function prepareDataFromCache(?array $data): ?array;

    /**
     * Output data from PageCache
     *
     * @param array $data
     *
     * @deprecated Use Cache::outputData() instead
     */
    public static function outputData(array $data): void;

    /**
     * Determines if request is on the admin site
     *
     * @return bool
     */
    public static function isAdmin(): bool;

    public static function getNonce(string $id): string;
}
