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

namespace JchOptimize\WordPress\View;

use _JchOptimizeVendor\V91\Joomla\Registry\Registry;
use _JchOptimizeVendor\V91\Joomla\Renderer\RendererInterface;
use JchOptimize\Core\Mvc\View;

use function __;
use function json_encode;
use function wp_add_inline_script;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_register_script;
use function wp_register_style;

use const JCH_PLUGIN_URL;

class OptimizeImageHtml extends View
{
    public function __construct(private Registry $params, RendererInterface $renderer)
    {
        parent::__construct($renderer);
    }

    public function loadResources(): void
    {
        wp_register_style('jch-filetree-css', JCH_PLUGIN_URL . 'media/filetree/jquery.filetree.css', [], JCH_VERSION);

        wp_register_script(
            'jch-filetree-js',
            JCH_PLUGIN_URL . 'media/filetree/jquery.filetree.js',
            ['jquery', 'jch-platformwordpress-js'],
            JCH_VERSION,
            true
        );

        $filetree_nonce = wp_create_nonce('jch_optimize_filetree');
        $js = <<<JS
const jch_filetree_url_nonce = '{$filetree_nonce}';
JS;

        wp_add_inline_script('jch-platformwordpress-js', $js, 'before');

        wp_register_script('jch-uuid', JCH_PLUGIN_URL . 'media/uuid/uuidv4.js');
        wp_enqueue_script('jch-uuid');

        if (JCH_PRO) {
            wp_register_style('jch-progressbar-css', JCH_PLUGIN_URL . 'media/jquery-ui/jquery-ui.css', [], JCH_VERSION);
            wp_register_script('jch-optimizeimage-js', JCH_PLUGIN_URL . 'media/core/js/optimize-image.js', [
                'jquery',
                'jch-adminutility-js',
                'jch-platformwordpress-js',
                'jch-bootstrap-js',
                'jch-uuid'
            ], JCH_VERSION, true);
            wp_register_script(
                'jch-progressbar-js',
                JCH_PLUGIN_URL . 'media/jquery-ui/jquery-ui.js',
                ['jquery'],
                JCH_VERSION,
                true
            );

            wp_enqueue_style('jch-progressbar-css');
            wp_enqueue_script('jquery-ui-progressbar');
            wp_enqueue_script('jch-optimizeimage-js');
        }

        wp_enqueue_style('jch-filetree-css');

        wp_enqueue_script('jch-filetree-js');

        $jch_params = json_encode([
                'auth' => [
                    'dlid' => $this->params->get('pro_downloadid', ''),
                    'secret' => '11e603aa',
                ],
                'resize_mode' => $this->params->get('pro_api_resize_mode', '1') ? 'auto' : 'manual',
                'webp' => (bool)$this->params->get('pro_next_gen_images', '1'),
                'lossy' => (bool)$this->params->get('lossy', '1'),
                'save_metadata' => (bool)$this->params->get('save_metadata', '0'),
                'quality' => $this->params->get('quality', '85'),
                'cropgravity' => $this->params->get('cropgravity', []),
                'responsive' => (bool)$this->params->get('pro_gen_responsive_images', '1')
            ]);


        $jch_message = __('Please open a directory to optimize images.', 'jch-optimize');
        $jch_noproid = __('Please enter your Download ID on the Configurations tab.', 'jch-optimize');

        $script =  <<<JS
const jch_message = '{$jch_message}'
const jch_noproid = '{$jch_noproid}'
const jch_params = JSON.parse('{$jch_params}')
JS;
        wp_add_inline_script('jch-platformwordpress-js', $script, 'before');

    }
}
