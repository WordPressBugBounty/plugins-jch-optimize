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

namespace JchOptimize\WordPress\Platform;

defined('_WP_EXEC') or die('Restricted access');

use JchOptimize\Core\Helper;
use JchOptimize\Core\Platform\ProfilerInterface;

use function __;
use function function_exists;
use function is_super_admin;
use function number_format;
use function number_format_i18n;
use function timer_stop;

class Profiler implements ProfilerInterface
{
    /**
     *
     * @param   string  $html
     * @param   bool    $isAmpPage
     */
    public function attachProfiler(&$html, $isAmpPage = false): void
    {
        if (! is_super_admin() || $isAmpPage) {
            return;
        }

        $items = Profiler::mark(true);

        $node = self::getAdminBarNodeBegin() . $items . self::getAdminBarNodeEnd();
        $cdata = Helper::isXhtml($html) ? '/*<![CDATA[*/' : '';

        $script = <<<HTML
<script>{$cdata}
    const li = document.getElementById('wp-admin-bar-jch-optimize-profiler')
    if (li !== null){
        li.classList.add('menupop')
        li.innerHTML = '{$node}'
    }
{$cdata}</script>
HTML;

        $html = str_replace('</body>', $script . '</body>', $html);
    }

    /**
     *
     * @staticvar string $item
     *
     * @param string|true $text
     *
     * @return null|string
     */
    public function mark($text): ?string
    {
        static $item = '';

        if ($text === true) {
            return $item;
        }

        static $prevTime = 0.0;
        if ($prevTime === 0.0) {
            $prevTime = microtime(true);
        }
        $currentTime = microtime(true);
        $timeInterval = ($currentTime - $prevTime) * 1000;
        $prevTime = $currentTime;

        $pageLoadTime = (float)timer_stop();

        $timeIntervalFormatted = (function_exists('number_format_i18n'))
            ? number_format_i18n($timeInterval, 3)
            : number_format($timeInterval, 3);

        $item .= self::addAdminBarItem($pageLoadTime . '  (+' . $timeIntervalFormatted . 'ms) - ' . $text);

        return null;
    }

    /**
     *
     * @param   string  $item
     *
     * @return string
     */
    protected static function addAdminBarItem(string $item): string
    {
        static $counter = 0;
        $counter++;

        return <<<HTML
<li id="wp-admin-bar-jch-optimize-profiler-item{$counter}"><div class="ab-item ab-empty-item"><a class="ab-item">{$item}</a></div></li>
HTML;
    }

    /**
     *
     * @return string
     */
    protected static function getAdminBarNodeBegin(): string
    {
        $profiler = __('Profiler', 'jch-optimize');

        return <<<HTML
<div class="ab-item ab-empty-item" aria-haspopup="true"><span class="wp-admin-bar-arrow" aria-hidden="true"></span><span class="dashicons dashicons-dashboard jch-ms-icon"></span><span>{$profiler}</span></div><div class="ab-sub-wrapper"><ul id="wp-admin-bar-jch-optimize-profiler-default" class="ab-submenu" style="overflow:auto;max-height: 600px;">
HTML;
    }

    /**
     *
     * @return string
     */
    protected static function getAdminBarNodeEnd(): string
    {
        return '</ul></div>';
    }

    /**
     *
     * @param   string  $text
     * @param   bool    $mark
     */
    public function start($text, $mark = false): void
    {
        if ($mark) {
            self::mark('before' . $text);
        }
    }

    /**
     *
     * @param   string  $text
     * @param   bool    $mark
     */
    public function stop($text, $mark = false): void
    {
        if ($mark) {
            self::mark('after' . $text);
        }
    }
}
