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

use JchOptimize\Core\Platform\PluginInterface;
use JchOptimize\Core\Registry;

use function get_option;
use function update_option;

defined('_WP_EXEC') or die('Restricted access');

class Plugin implements PluginInterface
{
    protected static ?string $plugin = null;

    public function getPluginId(): void
    {
    }

    public function getPlugin(): void
    {
    }

    public function saveSettings(Registry $params): void
    {
        $options = $params->toArray();

        update_option('jch-optimize_settings', $options);
    }

    public function getPluginParams(): Registry
    {
        /** @var array $options */
        $options = get_option('jch-optimize_settings');

        return new Registry($options);
    }
}
