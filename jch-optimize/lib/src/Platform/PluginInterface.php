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

interface PluginInterface
{
    public function getPluginId();

    public function getPlugin();

    public function saveSettings(Registry $params);

    public function getPluginParams();
}
