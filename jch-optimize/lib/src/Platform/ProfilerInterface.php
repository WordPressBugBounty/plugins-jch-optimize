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

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

interface ProfilerInterface
{
    public function mark($text);

    public function attachProfiler(&$html, $isAmpPage = false);

    public function start($text, $mark = false);

    public function stop($text, $mark = false);
}
