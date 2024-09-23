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

use function wp_add_inline_script;
use function wp_enqueue_script;
use function wp_register_script;

use const JCH_PLUGIN_URL;
use const JCH_VERSION;

class MainHtml extends View
{
    public function loadResources(): void
    {
        wp_register_script(
            'jch-resizesensor-lib-js',
            JCH_PLUGIN_URL . 'media/css-element-queries/ResizeSensor.js',
            ['jquery'],
            JCH_VERSION,
            true
        );
        wp_register_script(
            'jch-resizesensor-js',
            JCH_PLUGIN_URL . 'media/core/js/resize-sensor.js',
            ['jch-resizesensor-lib-js'],
            JCH_VERSION,
            true
        );
        wp_register_script(
            'jch-fileupload-js',
            JCH_PLUGIN_URL . 'media/core/js/file_upload.js',
            ['jch-bootstrap-js'],
            JCH_VERSION,
            true
        );


        wp_enqueue_script('jch-resizesensor-lib-js');
        wp_enqueue_script('jch-resizesensor-js');
        wp_enqueue_script('jch-fileupload-js');

        $js = <<<JS
window.addEventListener('DOMContentLoaded', (event) => {
    jchPlatform.getCacheInfo();
});
JS;
        wp_add_inline_script('jch-platformwordpress-js', $js);

    }
}
