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

namespace JchOptimize\WordPress\View;

use JchOptimize\Core\Mvc\View;

use function wp_add_inline_style;
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
        wp_register_style(
            'jch-multiselect',
            JCH_PLUGIN_URL . 'media/core/css/multiselect.css',
            [],
            JCH_VERSION
        );
        wp_register_style(
            'jch-wp-multiselect',
            JCH_PLUGIN_URL . 'media/css/wp-multiselect.css',
            ['jch-multiselect'],
            JCH_VERSION
        );
        wp_register_style(
            'jch-choices',
            JCH_PLUGIN_URL . 'media/choices.js/styles/choices.css',
            [],
            JCH_VERSION
        );

        wp_enqueue_style('jch-multiselect');
        wp_enqueue_style('jch-wp-multiselect');
        wp_enqueue_style('jch-choices');

        wp_register_script('jch-tab-state', JCH_PLUGIN_URL . 'media/js/tabs-state.js', [
            'jquery',
            'jch-bootstrap'
        ], JCH_VERSION, ['in_footer' => false]);
        wp_register_script(
            'jch-sticky-overlap-observer',
            JCH_PLUGIN_URL . 'media/js/sticky-overlap-observer.js',
            [],
            JCH_VERSION,
        );
        wp_register_script('jch-multiselect', JCH_PLUGIN_URL . 'media/core/js/multiselect.js', [
            'jquery',
            'jch-admin-utility',
            'jch-platform-wordpress'
        ],JCH_VERSION, ['in_footer' => false]);
        wp_register_script(
            'jch-choices',
            JCH_PLUGIN_URL . 'media/choices.js/scripts/choices.min.js',
            [],
            JCH_VERSION,
            ['in_footer' => false]
        );

        wp_enqueue_script('jch-tab-state');
        wp_enqueue_script('jch-sticky-overlap-observer');
        wp_enqueue_script('jch-multiselect');
        wp_enqueue_script('jch-choices');

        if (JCH_PRO) {
            wp_register_script(
                'jch-page-cache-form-control',
                JCH_PLUGIN_URL . 'media/js/pagecache-form-control.js',
                ['jquery'],
                JCH_VERSION,
                ['in_footer' => true]
            );

            wp_enqueue_script('jch-page-cache-form-control');
        }

        wp_add_inline_style('jch-wordpress', '*{overflow-anchor: none}');
    }
}
