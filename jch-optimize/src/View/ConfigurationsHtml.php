<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2021 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\View;

use JchOptimize\Core\Mvc\View;

use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_register_script;
use function wp_register_style;

use const JCH_PLUGIN_URL;
use const JCH_VERSION;

class ConfigurationsHtml extends View
{
    public function loadResources(): void
    {
        wp_register_style('jch-excludesjs-css', JCH_PLUGIN_URL . 'media/css/js-excludes.css');
        wp_register_script('jch-tabstate-js', JCH_PLUGIN_URL . 'media/js/tabs-state.js', [
                'jquery',
                'jch-bootstrap-js'
        ], JCH_VERSION, true);

        wp_enqueue_style('jch-excludesjs-css');
        wp_enqueue_script('jch-tabstate-js');

        if (JCH_PRO) {
            wp_register_script(
                'jch-pagecacheformcontrol-js',
                JCH_PLUGIN_URL . 'media/js/pagecache-form-control.js',
                ['jquery', 'jch-chosen-js'],
                JCH_VERSION,
                true
            );

            wp_enqueue_script('jch-pagecacheformcontrol-js');
        }
    }
}
