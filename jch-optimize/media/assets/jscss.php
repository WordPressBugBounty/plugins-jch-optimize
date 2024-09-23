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

use JchOptimize\Core\Output;

include dirname(__FILE__, 3) . '/dir.php';

const SHORTINIT = true;

if ( ! isset($wp_did_header)) {
    $wp_did_header = true;

    require_once($DIR . 'wp-load.php');
}

require_once(ABSPATH . WPINC . '/formatting.php');
require_once(ABSPATH . WPINC . '/link-template.php');
require_once(ABSPATH . WPINC . '/l10n.php');

wp_plugin_directory_constants();

$GLOBALS['wp_plugin_paths'] = array();

$plugin = WP_PLUGIN_DIR . '/jch-optimize/jch-optimize.php';

if ( ! file_exists($plugin)) {
    exit('Plugin not found');
}

wp_register_plugin_realpath($plugin);

define('_WP_EXEC', '1');

define('JCH_PLUGIN_FILE', $plugin);

define('JCH_PLUGIN_URL', plugin_dir_url(JCH_PLUGIN_FILE));
define('JCH_PLUGIN_DIR', plugin_dir_path(JCH_PLUGIN_FILE));
define('JCH_CACHE_DIR', WP_CONTENT_DIR . '/cache/jch-optimize/');
define('JCH_CACHE_URL', content_url() . '/cache/jch-optimize/');

$pluginDir = dirname($plugin);

require_once $pluginDir . '/autoload.php';

try {
    Output::getCombinedFile();
} catch (Exception $e) {
}

