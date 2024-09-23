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

use JchOptimize\Core\Interfaces\Excludes as ExcludesInterface;

use function preg_match;

defined('_WP_EXEC') or die('Restricted access');

class Excludes implements ExcludesInterface
{

    /**
     * @param   string  $type
     * @param   string  $section
     *
     * @return array
     */
    public static function body(string $type, string $section = 'file'): array
    {
        if ($type == 'js') {
            if ($section == 'script') {
                return array();
            } else {
                return array('js.stripe.com');
            }
        }

        if ($type == 'css') {
            return array();
        }

        return[];
    }

    /**
     *
     * @return string
     */
    public static function extensions(): string
    {
        return Paths::rewriteBaseFolder();
    }

    /**
     * @param   string  $type
     * @param   string  $section
     *
     * @return array
     */
    public static function head(string $type, string $section = 'file'): array
    {
        if ($type == 'js') {
            if ($section == 'script') {
                return array();
            } else {
                return array('js.stripe.com');
            }
        }

        if ($type == 'css') {
            return array();
        }

        return [];
    }

    /**
     * @param   string  $url
     *
     * @return bool
     */
    public static function editors(string $url): bool
    {
        return (bool)preg_match('#/editors/#i', $url);
    }

    public static function smartCombine(): array
    {
        return [
                'wp-includes/',
                'wp-content/themes/',
                '.'
        ];
    }
}
