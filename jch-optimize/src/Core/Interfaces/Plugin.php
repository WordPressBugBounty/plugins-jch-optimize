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

interface Plugin
{
    public static function getPluginId();

    public static function getPlugin();

    public static function saveSettings(Registry $params);

    public static function getPluginParams();
}
