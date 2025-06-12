<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2025 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\WordPress\Model;

use JchOptimize\Core\Registry;
use JchOptimize\WordPress\Plugin\Updater;

use function add_query_arg;
use function admin_url;
use function strlen;

class NotificationIcons
{
    public function __construct(private Registry $params, private Updater $updater)
    {
    }

    public function getNotificationIcons(): array
    {
        $buttons = [];

        $buttons[0]['link'] = admin_url('update-core.php');
        $buttons[0]['icon'] = 'fa fa-plug';
        $buttons[0]['id'] = 'plugin-status';

        if($this->updater->updateAvailable()) {
            $buttons[0]['class'] = ['danger'];
            $buttons[0]['name'] = __('Plugin update available', 'jch-optimize');
        } else {
            $buttons[0]['class'] = ['success'];
            $buttons[0]['name'] = __('Plugin is up to date', 'jch-optimize');
        }

        $configurationsUrl = add_query_arg(
            ['page' => 'jch_optimize', 'tab' => 'configurations'],
            admin_url('options-general.php')
        );

        if (JCH_PRO) {
            $buttons[1]['id'] = 'download-id-status';
            $buttons[1]['link'] = "{$configurationsUrl}#general";
            $buttons[1]['icon'] = 'fa fa-id-badge';

            if (strlen($this->params->get('pro_downloadid', '')) < 32) {
                $buttons[1]['class'] = ['danger'];
                $buttons[1]['name'] = __('Download ID missing', 'jch-optimize');
            } else {
                $buttons[1]['class'] = ['success'];
                $buttons[1]['name'] = __('Download ID entered', 'jch-optimize');
            }
        }

        $buttons[2]['id'] = 'page-cache-status';
        $buttons[2]['link'] = "{$configurationsUrl}#page-cache";
        $buttons[2]['icon'] = 'fa fa-archive';

        if ($this->params->get('cache_enable')) {
            $buttons[2]['class' ] = ['success'];
            $buttons[2]['name'] = __('Page Cache enabled', 'jch-optimize');
        } else {
            $buttons[2]['class'] = ['danger'];
            $buttons[2]['name'] = __('Page Cache disabled', 'jch-optimize');
        }

        return $buttons;
    }
}
