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

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

interface Profiler
{
    public static function mark($text);

    public static function attachProfiler(&$html, $isAmpPage = false);

    public static function start($text, $mark = false);

    public static function stop($text, $mark = false);
}
