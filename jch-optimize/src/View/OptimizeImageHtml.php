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
        wp_register_style('jch-file-tree', JCH_PLUGIN_URL . 'media/filetree/jquery.filetree.css', [], JCH_VERSION);

        wp_register_script(
            'jch-file-tree',
            JCH_PLUGIN_URL . 'media/filetree/jquery.filetree.js',
            ['jquery', 'jch-platform-wordpress'],
            JCH_VERSION,
            true
        );

        $filetree_nonce = wp_create_nonce('jch_optimize_filetree');
        $js = <<<JS
const jch_filetree_url_nonce = '{$filetree_nonce}';
JS;

        wp_add_inline_script('jch-platform-wordpress', $js, 'before');

        if (JCH_PRO) {
            wp_register_script_module(
                'jch-optimize-image',
                JCH_PLUGIN_URL . 'media/core/js/optimize-image.js',
                [],
                JCH_VERSION
            );
            wp_enqueue_script_module('jch-optimize-image');
        }

        wp_enqueue_style('jch-file-tree');
        wp_enqueue_script('jch-file-tree');

        $params = json_encode([
            'auth'          => [
                'dlid' => $this->params->get('pro_downloadid', ''),
                'secret' => '11e603aa',
            ],
            'resize_mode'   => $this->params->get('pro_api_resize_mode', '1') ? 'auto' : 'manual',
            'webp'          => (bool)$this->params->get('pro_next_gen_images', '1'),
            'avif'          => (bool)$this->params->get('gen_avif_images', '1'),
            'lossy'         => (bool)$this->params->get('lossy', '1'),
            'save_metadata' => (bool)$this->params->get('save_metadata', '0'),
            'quality'       => $this->params->get('quality', '85'),
            'cropgravity'   => $this->params->get('cropgravity', []),
            'responsive'    => (bool)$this->params->get('pro_gen_responsive_images', '1')
        ]);


        $message = __('Please open a directory to optimize images.', 'jch-optimize');
        $noProID = __('Please enter your Download ID on the Configurations tab.', 'jch-optimize');

        $script = <<<JS
window.jchOptimizeImageData ={
    message : '{$message}',
    noproid: '{$noProID}',
    params: JSON.parse('{$params}')
}
JS;
        wp_add_inline_script('jch-platform-wordpress', $script, 'before');
    }
}
