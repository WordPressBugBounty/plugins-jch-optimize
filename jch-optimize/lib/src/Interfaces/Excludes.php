<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads.
 *
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Interfaces;

\defined('_JCH_EXEC') or exit('Restricted access');
interface Excludes
{
    public static function extensions(): string;

    public static function head(string $type, string $section = 'file'): array;

    public static function body(string $type, string $section = 'file'): array;

    public static function editors(string $url): bool;

    public static function smartCombine();
}
