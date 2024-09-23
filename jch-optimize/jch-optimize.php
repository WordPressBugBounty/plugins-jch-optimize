<?php

/**
 * Plugin Name: JCH Optimize
 * Plugin URI: http://www.jch-optimize.net/
 * Description: Boost your WordPress site's performance with JCH Optimize as measured on PageSpeed
 * Version: 4.2.1
 * Author: Samuel Marshall
 * License: GNU/GPLv3
 * Text Domain: jch-optimize
 * Domain Path: /languages
 */

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

use JchOptimize\ContainerFactory;
use JchOptimize\Plugin\Loader;

if (version_compare(PHP_VERSION, '8.0', 'lt')) {
    function jchoptimize_update_php_message()
    {
        $message = sprintf(
            __(
                'JCH Optimize requires at least PHP 8.0. You current version is %s. Please update your PHP version or deactivate the plugin.',
                'jch-optimize'
            ),
            PHP_VERSION
        );
        echo <<<HTML
<div class="notice notice-warning is-dismissible"><p>{$message}</p></div>
HTML;
    }

    add_action('admin_notices', 'jchoptimize_update_php_message');

    $mu_folder = ABSPATH . 'wp-content/mu-plugins';
    if (defined('WPMU_PLUGIN_DIR') && WPMU_PLUGIN_DIR) {
        $mu_folder = WPMU_PLUGIN_DIR;
    }

    $mu_plugin = $mu_folder . '/jch-optimize-mode-switcher.php';

    if (file_exists($mu_plugin)) {
        unlink($mu_plugin);
    }

    return;
}

$jch_no_optimize = false;

define('_WP_EXEC', '1');

define('JCH_PLUGIN_FILE', __FILE__);
define('JCH_PLUGIN_URL', plugin_dir_url(JCH_PLUGIN_FILE));
define('JCH_PLUGIN_DIR', plugin_dir_path(JCH_PLUGIN_FILE));
define('JCH_CACHE_DIR', WP_CONTENT_DIR . '/cache/jch-optimize/');
define('JCH_CACHE_URL', content_url() . '/cache/jch-optimize/');

require_once(JCH_PLUGIN_DIR . 'autoload.php');

try {
    $container = ContainerFactory::getContainer();
    $loader = $container->get(Loader::class);
    /**
     * Upgrade settings from versions less than 3.0.0
     */
    $loader->preboot_init();
    /**
     * Initialize and run plugin
     */
    $loader->init();
} catch (Exception $e) {
    function jchoptimize_initialize_error()
    {
        $message = __(
            'An error occurred while trying to initialize the JCH Optimize plugin. Please deactivate the plugin and report all errors to the developer.',
            'jch-optimize'
        );
        echo <<<HTML
<div class="notice notice-error is-dismissible"><p>{$message}</p></div>
HTML;
    }

    add_action('admin_notices', 'jchoptimize_initialize_error');

    return;
}
