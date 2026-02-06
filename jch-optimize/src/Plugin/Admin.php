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

namespace JchOptimize\WordPress\Plugin;

use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareTrait;
use _JchOptimizeVendor\V91\Joomla\Input\Input;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareTrait;
use Exception;
use JchOptimize\Core\Admin\Ajax\Ajax;
use JchOptimize\Core\Mvc\Controller;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Registry;
use JchOptimize\WordPress\Container\ContainerFactory;
use JchOptimize\WordPress\Contracts\WillEnqueueAssets;
use JchOptimize\WordPress\Controller\PageCache;
use JchOptimize\WordPress\ControllerResolver;
use JchOptimize\WordPress\Html\Helper;
use JchOptimize\WordPress\Html\Renderer\Section;
use JchOptimize\WordPress\Html\Renderer\Setting;
use JchOptimize\WordPress\Html\TabSettings;
use JetBrains\PhpStorm\NoReturn;

use function __;
use function add_action;
use function add_options_page;
use function add_settings_field;
use function add_settings_section;
use function add_submenu_page;
use function admin_url;
use function check_admin_referer;
use function current_user_can;
use function delete_transient;
use function esc_url_raw;
use function get_transient;
use function plugin_basename;
use function register_setting;
use function wp_add_inline_script;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_register_script;
use function wp_register_style;

use const JCH_PRO;
use const JCH_VERSION;

class Admin implements LoggerAwareInterface, ContainerAwareInterface
{
    use LoggerAwareTrait;
    use ContainerAwareTrait;

    private string $hookSuffix = '';

    private ?Controller $resolvedController = null;

    public function __construct(
        private Registry $params,
        private ControllerResolver $controllerResolver,
        private PathsInterface $paths,
    ) {
    }

    public static function publishAdminNotices(): void
    {
        try {
            if ($messages = get_transient('jch-optimize_notices')) {
                foreach ($messages as $message) {
                    echo <<<HTML
<div class="notice notice-{$message['type']} is-dismissible"><p>{$message['message']}</p></div>
HTML;
                }

                delete_transient('jch-optimize_notices');
            }
        } catch (Exception $e) {
        }
    }

    public function addAdminMenu(): void
    {
        $menuTitle = JCH_PRO ? 'JCH Optimize Pro' : 'JCH Optimize';
        $this->hookSuffix = (string)add_options_page(
            __('JCH Optimize Settings', 'jch-optimize'),
            $menuTitle,
            'manage_options',
            'jch_optimize',
            [$this, 'loadAdminPage']
        );

        add_action('admin_enqueue_scripts', [$this, 'loadResourceFiles']);

        if ($this->hookSuffix !== '') {
            add_action("load-{$this->hookSuffix}", [$this, 'initializeSettings']);
        }

        add_action('admin_init', [$this, 'checkMessages']);
    }

    public function addPageCacheMenu(): void
    {
        if (current_user_can('manage_options')) {
            return;
        }

        $this->hookSuffix = (string)add_submenu_page(
            'tools.php',                               // parent
            __('JCH Optimize Page Cache', 'jch-optimize'),      // page title
            __('Page Cache', 'jch-optimize'),      // menu title
            'jch_clear_page_cache',                    // capability
            'jch_optimize_page_cache',                 // slug
            [$this, 'renderPageCacheOnly']
        );

        if ($this->hookSuffix !== '') {
            add_action("load-{$this->hookSuffix}", [$this, 'initializeSettings']);
            add_action("load-{$this->hookSuffix}", [
                $this->getContainer()->get(PageCache::class),
                'enqueueAssets'
            ]);
        }

        // Show a card on Tools → Available Tools
        add_action('tool_box', function () {
            if (!current_user_can('jch_clear_page_cache')) {
                return;
            }

            $url = menu_page_url('jch_optimize_page_cache', false);
            ?>
            <div class="card">
                <h2 class="title"><?php
                    esc_html_e('JCH Optimize Page Cache', 'jch-optimize'); ?></h2>
                <p><?php
                    esc_html_e('Delete specific cached pages without accessing full settings.', 'jch-optimize'); ?></p>
                <p><a href="<?php
                    echo esc_url($url); ?>">
                        <?php
                        esc_html_e('Open Page Cache', 'jch-optimize'); ?>
                    </a></p>
            </div>
            <?php
        });
    }


    public function loadAdminPage(): void
    {
        try {
                $this->resolvedController?->execute() ?? $this->controllerResolver->resolve();
        } catch (Exception $e) {
            $class = get_class($e);
            echo <<<HTML
<h1>Application Error</h1>
<p>Please submit the following error message and trace in a support request:</p>
<div class="alert alert-danger">  {$class} &mdash;  {$e->getMessage()}  </div>
<pre class="well"> {$e->getTraceAsString()} </pre>
HTML;
        }
    }

    public function renderPageCacheOnly(): void
    {
        $this->getContainer()->get(PageCache::class)
             ->setRedirect('tools.php?page=jch_optimize_page_cache')
             ->setLayout('page_cache_only.php')
             ->execute();
    }

    public function registerOptions(): void
    {
        //Buffer output to allow for redirection
        ob_start();

        register_setting('jchOptimizeOptionsPage', 'jch-optimize_settings', ['type' => 'array']);
    }

    /**
     * @param   string  $hookSuffix  The current admin page
     *
     * @return void
     */
    public function loadResourceFiles(string $hookSuffix): void
    {
        if ($this->hookSuffix !== $hookSuffix) {
            return;
        }

        $this->enqueueGlobalAssets();

        if ($this->resolvedController instanceof WillEnqueueAssets) {
            $this->resolvedController->enqueueAssets();
        }
    }

    private function enqueueGlobalAssets(): void
    {
        wp_enqueue_style('jch-bootstrap');
        wp_enqueue_style('jch-admin');
        wp_enqueue_style('jch-fonts');
        //wp_enqueue_style('jch-choices-base');
        wp_enqueue_style('jch-wordpress');
        wp_enqueue_style('jch-dashicons-wordpress');
        wp_enqueue_style('jch-dashicons');

        wp_enqueue_script('jch-platform-wordpress');
        wp_enqueue_script('jch-bootstrap');
        wp_enqueue_script('jch-admin-utility');

        wp_enqueue_script('jch-collapsible-js');
    }

    public function initializeSettings(): void
    {
        //Css files
        wp_register_style(
            'jch-bootstrap',
            JCH_PLUGIN_URL . 'media/bootstrap/css/bootstrap.min.css',
            [],
            JCH_VERSION
        );

        wp_register_style('jch-admin', JCH_PLUGIN_URL . 'media/core/css/admin.css', [], JCH_VERSION);
        wp_register_style('jch-fonts', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');
        //wp_register_style('jch-choices-base', JCH_PLUGIN_URL . 'media/choices.js/styles/base.css', [], JCH_VERSION);
        wp_register_style('jch-wordpress', JCH_PLUGIN_URL . 'media/css/wordpress.css', [], JCH_VERSION);
        wp_register_style(
            'jch-dashicons-wordpress',
            JCH_PLUGIN_URL . 'media/css/dashicons-wordpress.css',
            [],
            JCH_VERSION
        );
        wp_register_style(
            'jch-dashicons',
            JCH_PLUGIN_URL . 'media/core/css/dashicons.css',
            ['jch-dashicons-wordpress'],
            JCH_VERSION
        );

        //JavaScript files
        wp_register_script(
            'jch-bootstrap',
            JCH_PLUGIN_URL . 'media/bootstrap/js/bootstrap.bundle.min.js',
            ['jquery'],
            JCH_VERSION,
            true
        );
        wp_register_script(
            'jch-platform-wordpress',
            JCH_PLUGIN_URL . 'media/js/platform-wordpress.js',
            ['jquery'],
            JCH_VERSION,
            true
        );
        wp_register_script(
            'jch-admin-utility',
            JCH_PLUGIN_URL . 'media/core/js/admin-utility.js',
            ['jquery'],
            JCH_VERSION,
            true
        );

        $this->resolvedController = $this->controllerResolver->getController();

        $loader_image = $this->paths->mediaUrl() . '/core/images/loader.gif';
        $multiselect_nonce = wp_create_nonce('jch_optimize_multiselect');
        $optimize_image_nonce = wp_create_nonce('jch_optimize_image');
        $imageLoaderJs = <<<JS
const jch_optimize_image_url_nonce = '{$optimize_image_nonce}';
const jch_loader_image_url = "{$loader_image}";
const jch_multiselect_url_nonce = '{$multiselect_nonce}';
JS;

        wp_add_inline_script('jch-platform-wordpress', $imageLoaderJs, 'before');

        $popoverJs = <<<JS
window.onload = function(){
	var popoverTriggerList = [].slice.call(document.querySelectorAll('.hasPopover'));
   		var popoverList = popoverTriggerList.map(function(popoverTriggerEl){
    		return new bootstrap.Popover(popoverTriggerEl, {
			html: true,
			container: '#jch-bs-admin-ui',
			placement: 'right',
			trigger: 'hover focus'
		});
	});
}
JS;
        wp_add_inline_script('jch-bootstrap', $popoverJs);

        /** @psalm-var array<string, array<string, array{0:string, 1:string, 2?:bool}>> $aSettingsArray */
        $aSettingsArray = TabSettings::getSettingsArray();

        foreach ($aSettingsArray as $section => $aSettings) {
            add_settings_section('jch-optimize_' . $section . '_section', '', [
                Section::class,
                $section
            ], 'jchOptimizeOptionsPage');


            foreach ($aSettings as $setting => $args) {
                list($title, $description, $new, $class) = array_pad($args, 4, false);

                $id = 'jch-optimize_' . $setting;
                $title = Helper::description($title, $description, $new);
                $args = [];

                if ($class !== false) {
                    $args['class'] = $class;
                }

                add_settings_field($id, $title, [
                    Setting::class,
                    $setting
                ], 'jchOptimizeOptionsPage', 'jch-optimize_' . $section . '_section', $args);
            }
        }
    }

    public function getCurrentAdminUri(): string
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $uri = preg_replace('|^.*/wp-admin/|i', '', $uri);

        if (!$uri) {
            return '';
        }

        return $uri;
    }

    public function checkMessages(): void
    {
        if (get_transient('jch-optimize_notices')) {
            add_action('admin_notices', [__CLASS__, 'publishAdminNotices']);
        }
    }

    /**
     * @param   string[]  $actionLinks  An array of plugin action links. By default, this can include 'activate', 'deactivate', and 'delete'.
     *                                  With Multisite active this can also include 'network_active' and 'network_only' items.
     * @param   string    $pluginFile   Path to the plugin file relative to the plugins directory
     *
     * @return array
     */
    public function loadActionLinks(array $actionLinks, string $pluginFile): array
    {
        static $this_plugin;

        if (!$this_plugin) {
            $this_plugin = plugin_basename(JCH_PLUGIN_FILE);
        }

        if ($pluginFile == $this_plugin) {
            $settingsLink = '<a href="' . admin_url('options-general.php?page=jch_optimize') . '">' . __(
                'Settings'
            ) . '</a>';
            array_unshift($actionLinks, $settingsLink);
        }

        return $actionLinks;
    }

    #[NoReturn]
    public function doAjaxFileTree(): void
    {
        check_admin_referer('jch_optimize_filetree');

        if (current_user_can('manage_options')) {
            echo Ajax::getInstance('FileTree')->run();
        }

        die();
    }

    #[NoReturn]
    public function doAjaxMultiSelect(): void
    {
        check_admin_referer('jch_optimize_multiselect');
        $_POST = wp_unslash($_POST);
        $_REQUEST = wp_unslash($_REQUEST);

        if (current_user_can('manage_options')) {
            echo Ajax::getInstance('MultiSelect')->run();
        }

        die();
    }

    #[NoReturn]
    public function doAjaxOptimizeImages(): void
    {
        check_admin_referer('jch_optimize_image');

        if (current_user_can('manage_options')) {
            Ajax::getInstance('OptimizeImage')->run();
        }

        die();
    }

    #[NoReturn]
    public function doAjaxConfigureSettings(): void
    {
        if (current_user_can('manage_options')) {
            $container = ContainerFactory::getInstance();
            $container->get(ControllerResolver::class)->resolve();
        }

        die();
    }

    #[NoReturn]
    public function doAjaxOnClickIcon(): void
    {
        if (current_user_can('manage_options')) {
            $container = ContainerFactory::getInstance();
            check_admin_referer($container->get(Input::class)->get('task'));
            $container->get(ControllerResolver::class)->resolve();
        }

        die();
    }

    #[NoReturn]
    public function doAjaxSmartCombine(): void
    {
        if (current_user_can('manage_options')) {
            echo Ajax::getInstance('SmartCombine')->run();
        }

        die();
    }

    #[NoReturn]
    public function doAjaxGetCacheInfo(): void
    {
        $container = ContainerFactory::getInstance();
        $container->get(Input::class)->def('task', 'getcacheinfo');
        $container->get(ControllerResolver::class)->resolve();

        die();
    }

    #[NoReturn]
    public function doAjaxJchConfigureJsTableBody(): void
    {
        $container = ContainerFactory::getInstance();
        $container->get(Input::class)->def('task', 'criticaljstablebody');
        $container->get(ControllerResolver::class)->resolve();

        die();
    }

    #[NoReturn]
    public function doAjaxJchConfigureJsAutoSave(): void
    {
        $container = ContainerFactory::getInstance();
        $container->get(Input::class)->def('task', 'criticaljsautosave');
        $container->get(ControllerResolver::class)->resolve();

        die();
    }

    #[NoReturn]
    public function doAjaxJchCfVerify(): void
    {
        check_admin_referer('jch_optimize_verify_cf_token');
        if (current_user_can('manage_options')) {
            $container = ContainerFactory::getInstance();
            $container->get(Input::class)->def('task', 'CfVerifyToken');
            $container->get(ControllerResolver::class)->resolve();
        }

        die();
    }
}
