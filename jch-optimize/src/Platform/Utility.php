<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2020 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Platform;

use JchOptimize\Core\Interfaces\Utility as UtilityInterface;
use JchOptimize\Core\Registry;
use stdClass;

use function __;
use function add_action;
use function array_search;
use function count;
use function function_exists;
use function header;
use function headers_list;
use function is_admin;
use function is_user_logged_in;
use function preg_match_all;
use function strripos;
use function wp_create_nonce;
use function wp_is_mobile;

defined('_WP_EXEC') or die('Restricted access');

class Utility implements UtilityInterface
{
    /**
     *
     * @param   string  $text
     *
     * @return string
     */
    public static function translate(string $text): string
    {
        return __($text, 'jch-optimize');
    }

    /**
     * Checks if user is not logged in
     *
     */
    public static function isGuest(): bool
    {
        if (defined('TEST_SITE_ROOT')) {
            return true;
        }

        if (function_exists('is_user_logged_in')) {
            return !is_user_logged_in();
        }

        return false;
    }

    /**
     * @param   array  $headers
     *
     * @return void
     */
    public static function sendHeaders(array $headers): void
    {
        /** @psalm-var array<string, string> $headers */
        if (!empty($headers)) {
            foreach ($headers as $header => $value) {
                header($header . ': ' . $value, false);
            }
        }
    }

    public static function userAgent($userAgent): stdClass
    {
        global $is_chrome, $is_IE, $is_edge, $is_safari, $is_opera, $is_gecko, $is_winIE, $is_macIE, $is_iphone;

        $oUA = new stdClass();
        $oUA->browser = 'Unknown';
        $oUA->browserVersion = 'Unknown';
        $oUA->os = 'Unknown';

        if ($is_chrome) {
            $oUA->browser = 'Chrome';
        } elseif ($is_gecko) {
            $oUA->browser = 'Firefox';
        } elseif ($is_safari) {
            $oUA->browser = 'Safari';
        } elseif ($is_edge) {
            $oUA->browser = 'Edge';
        } elseif ($is_IE) {
            $oUA->browser = 'Internet Explorer';
        } elseif ($is_opera) {
            $oUA->browser = 'Opera';
        }


        if ($oUA->browser != 'Unknown') {
            // Build the REGEX pattern to match the browser version string within the user agent string.
            $pattern = '#(?<browser>Version|' . $oUA->browser . ')[/ :]+(?<version>[0-9.|a-zA-Z.]*)#';

            // Attempt to find version strings in the user agent string.
            $matches = array();

            if (preg_match_all($pattern, $userAgent, $matches)) {
                // Do we have both a Version and browser match?
                if (count($matches['browser']) == 2) {
                    // See whether Version or browser came first, and use the number accordingly.
                    if (strripos($userAgent, 'Version') < strripos($userAgent, $oUA->browser)) {
                        $oUA->browserVersion = $matches['version'][0];
                    } else {
                        $oUA->browserVersion = $matches['version'][1];
                    }
                } elseif (count($matches['browser']) > 2) {
                    $key = array_search('Version', $matches['browser']);

                    if ($key) {
                        $oUA->browserVersion = $matches['version'][$key];
                    }
                } else {
                    // We only have a Version or a browser so use what we have.
                    $oUA->browserVersion = $matches['version'][0];
                }
            }
        }

        if ($is_winIE) {
            $oUA->os = 'Windows';
        } elseif ($is_macIE) {
            $oUA->os = 'Mac';
        } elseif ($is_iphone) {
            $oUA->os = 'iOS';
        }


        return $oUA;
    }

    public static function bsTooltipContentAttribute(): string
    {
        return 'data-bs-content';
    }

    /**
     * @param   Registry  $params
     * @param   bool      $nativeCache
     *
     * @return bool
     * @deprecated Use Cache::isPageCacheEnabled()
     */
    public static function isPageCacheEnabled(Registry $params, bool $nativeCache = false): bool
    {
        return (bool)$params->get('cache_enable', '0');
    }

    public static function isMobile(): bool
    {
        return wp_is_mobile();
    }

    /**
     * @param   Registry  $params
     *
     * @return string
     * @deprecated Use Cache::getCacheStorage()
     */
    public static function getCacheStorage(Registry $params): string
    {
        return $params->get('pro_cache_storage_adapter', 'filesystem');
    }

    public static function getHeaders(): array
    {
        return headers_list();
    }

    public static function publishAdminMessages(string $message, string $messageType): void
    {
        add_action('admin_notices', function () use ($message, $messageType) {
            echo <<<HTML
<div class="notice notice-{$messageType} is-dismissible"><p>{$message}</p></div>
HTML;
        });
    }

    public static function getLogsPath(): string
    {
        return JCH_PLUGIN_DIR . 'logs';
    }

    public static function isSiteGzipEnabled(): bool
    {
        return false;
    }

    /**
     * @param   array  $data
     *
     * @return void
     * @deprecated  Use Cache::outputData()
     */
    public static function outputData(array $data): void
    {
        /** @psalm-var array{headers:array<array-key, string>, body: string} $data */
        if (!empty($data['headers'])) {
            foreach ($data['headers'] as $header) {
                header($header);
            }
        }

        echo $data['body'];

        exit();
    }

    /**
     * @param   array|null  $data
     *
     * @return array|null
     * @deprecated Use Cache::prepareDataFromCache()
     */
    public static function prepareDataFromCache(?array $data): ?array
    {
        return $data;
    }

    public static function isAdmin(): bool
    {
        return is_admin();
    }

    public static function getNonce(string $id): string
    {
        return wp_create_nonce($id);
    }
}
