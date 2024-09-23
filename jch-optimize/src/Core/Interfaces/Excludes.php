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

interface Excludes
{
    /**
     *
     * @return string
     */
    public static function extensions(): string;

    /**
     * @param string $type
     * @param string $section
     *
     * @return array
     */
    public static function head(string $type, string $section = 'file'): array;

    /**
     * @param string $type
     * @param string $section
     *
     * @return array
     */
    public static function body(string $type, string $section = 'file'): array;

    /**
     * @param string $url
     *
     * @return bool
     */
    public static function editors(string $url): bool;

    public static function smartCombine();
}
