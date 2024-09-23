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

namespace JchOptimize\Plugin;

use Exception;
use JchOptimize\ContainerFactory;
use JchOptimize\ControllerResolver;
use JchOptimize\Core\Admin\Ajax\Ajax;
use JchOptimize\Core\Admin\Ajax\Ajax as AdminAjax;
use JchOptimize\Core\Input;
use JchOptimize\Core\Psr\Log\LoggerAwareInterface;
use JchOptimize\Core\Psr\Log\LoggerAwareTrait;
use JchOptimize\Core\Registry;
use JchOptimize\Html\Helper;
use JchOptimize\Html\TabSettings;
use JchOptimize\Platform\Paths;
use WP_Admin_Bar;

use function __;
use function add_action;
use function add_options_page;
use function add_query_arg;
use function add_settings_field;
use function add_settings_section;
use function admin_url;
use function array_merge;
use function check_admin_referer;
use function current_user_can;
use function delete_transient;
use function esc_url_raw;
use function get_transient;
use function JchOptimize\base64_encode_url;
use function plugin_basename;
use function register_setting;
use function wp_add_inline_script;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_register_script;
use function wp_register_style;

use const JCH_PRO;

class Admin implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var Registry
     */
    private Registry $params;
    /**
     * @var ControllerResolver
     */
    private ControllerResolver $controllerResolver;

    public function __construct(Registry $params, ControllerResolver $controllerResolver)
    {
        $this->params = $params;
        $this->controllerResolver = $controllerResolver;
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
        $hook_suffix = add_options_page(
            __('JCH Optimize Settings', 'jch-optimize'),
            $menuTitle,
            'manage_options',
            'jch_optimize',
            [
                $this,
                'loadAdminPage'
            ]
        );

        add_action('admin_enqueue_scripts', [$this, 'loadResourceFiles']);

        if ($hook_suffix !== false) {
            add_action('admin_head-' . $hook_suffix, [$this, 'addScriptsToHead']);
            add_action('load-' . $hook_suffix, [$this, 'initializeSettings']);
        }

        add_action('admin_bar_menu', [$this, 'addMenuToAdminBar'], 101);
        add_action('admin_init', [$this, 'checkMessages']);
    }

    public function loadAdminPage(): void
    {
        try {
            $this->controllerResolver->resolve();
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
        if ('settings_page_jch_optimize' != $hookSuffix) {
            return;
        }

        wp_enqueue_style('jch-bootstrap-css');
        wp_enqueue_style('jch-verticaltabs-css');
        wp_enqueue_style('jch-admin-css');
        wp_enqueue_style('jch-fonts-css');
        wp_enqueue_style('jch-chosen-css');
        wp_enqueue_style('jch-wordpress-css');
        wp_enqueue_style('jch-filetree-css');

        wp_enqueue_script('jch-platformwordpress-js');
        wp_enqueue_script('jch-bootstrap-js');
        wp_enqueue_script('jch-adminutility-js');
        wp_enqueue_script('jch-multiselect-js');

        wp_enqueue_script('jch-chosen-js');
        wp_enqueue_script('jch-collapsible-js');
        wp_enqueue_script('jch-filetree-js');

        if (JCH_PRO) {
            wp_enqueue_style('jch-progressbar-css');
            wp_enqueue_script('jquery-ui-progressbar');
            wp_enqueue_script('jch-optimizeimage-js');
            wp_enqueue_script('jch-smartcombine-js');
        }
    }

    public function addScriptsToHead(): void
    {
        echo <<<HTML
		<style>
                    .chosen-container-multi .chosen-choices li.search-field input[type=text] {
                        height: 25px;
                    }

                    .chosen-container {
                        margin-right: 4px;
                    }

		</style>
		<script type="text/javascript">
		(function($){
                    function submitJchSettings() {
                        $("form.jch-settings-form").submit();
                    }
                })(jQuery);
                </script>
                    
HTML;
        if (JCH_PRO) {
            $optimizeImageUrl = add_query_arg(
                [
                    'page' => 'jch_optimize',
                    'view' => 'optimizeimage'
                ],
                admin_url('options-general.php')
            );

            $paramsArray = $this->params->toArray();
            $aApiParams = [
                'pro_downloadid'      => '',
                'hidden_api_secret'   => '11e603aa',
                'ignore_optimized'    => '1',
                'recursive'           => '1',
                'pro_api_resize_mode' => '1',
                'pro_next_gen_images' => '1',
                'lossy'               => '1',
                'save_metadata'       => '0'
            ];


            $jch_message = __('Please open a directory to optimize images.', 'jch-optimize');
            $jch_noproid = __('Please enter your Download ID on the Configurations tab.', 'jch-optimize');
            $jch_params = json_encode(array_intersect_key(array_merge($aApiParams, $paramsArray), $aApiParams));
            echo <<<HTML
		<script>
                    const jch_message = '{$jch_message}'
                    const jch_noproid = '{$jch_noproid}'
                    const jch_params = JSON.parse('{$jch_params}')
		</script>
HTML;
        }
    }

    public function initializeSettings(): void
    {
        //Css files
        wp_register_style(
            'jch-bootstrap-css',
            JCH_PLUGIN_URL . 'media/bootstrap/css/bootstrap.min.css',
            [],
            JCH_VERSION
        );

        wp_register_style('jch-admin-css', JCH_PLUGIN_URL . 'media/core/css/admin.css', [], JCH_VERSION);
        wp_register_style('jch-fonts-css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');
        wp_register_style('jch-chosen-css', JCH_PLUGIN_URL . 'media/chosen-js/chosen.css', [], JCH_VERSION);
        wp_register_style('jch-wordpress-css', JCH_PLUGIN_URL . 'media/css/wordpress.css', [], JCH_VERSION);
        wp_register_style('jch-filetree-css', JCH_PLUGIN_URL . 'media/filetree/jquery.filetree.css', [], JCH_VERSION);

        //JavaScript files
        wp_register_script(
            'jch-bootstrap-js',
            JCH_PLUGIN_URL . 'media/bootstrap/js/bootstrap.bundle.min.js',
            ['jquery'],
            JCH_VERSION,
            true
        );
        wp_register_script(
            'jch-platformwordpress-js',
            JCH_PLUGIN_URL . 'media/js/platform-wordpress.js',
            ['jquery'],
            JCH_VERSION,
            true
        );
        wp_register_script(
            'jch-adminutility-js',
            JCH_PLUGIN_URL . 'media/core/js/admin-utility.js',
            ['jquery'],
            JCH_VERSION,
            true
        );
        wp_register_script('jch-multiselect-js', JCH_PLUGIN_URL . 'media/core/js/multiselect.js', [
            'jquery',
            'jch-adminutility-js',
            'jch-platformwordpress-js'
        ], JCH_VERSION, true);
        wp_register_script(
            'jch-chosen-js',
            JCH_PLUGIN_URL . 'media/chosen-js/chosen.jquery.js',
            ['jquery'],
            JCH_VERSION,
            true
        );
        wp_register_script(
            'jch-filetree-js',
            JCH_PLUGIN_URL . 'media/filetree/jquery.filetree.js',
            ['jquery'],
            JCH_VERSION,
            true
        );

        $loader_image = Paths::mediaUrl() . '/core/images/loader.gif';
        $optimize_image_nonce = wp_create_nonce('jch_optimize_image');
        $multiselect_nonce = wp_create_nonce('jch_optimize_multiselect');
        $filetree_nonce = wp_create_nonce('jch_optimize_filetree');
        $imageLoaderJs = <<<JS
const jch_loader_image_url = "{$loader_image}";
const jch_optimize_image_url_nonce = '{$optimize_image_nonce}';
const jch_multiselect_url_nonce = '{$multiselect_nonce}';
const jch_filetree_url_nonce = '{$filetree_nonce}';
JS;

        wp_add_inline_script('jch-platformwordpress-js', $imageLoaderJs, 'before');

        $chosenJs = <<<JS
		(function($){
                   $(document).ready(function () {
                        $(".chzn-custom-value").chosen({
                             disable_search_threshold: 10,
                             width: "300px",
                             placeholder_text_multiple: "Select some options or add items to select"
                         });
                   });
                })(jQuery);
JS;

        wp_add_inline_script('jch-chosen-js', $chosenJs);

        $popoverJs = <<<JS
		window.onload = function(){
			var popoverTriggerList = [].slice.call(document.querySelectorAll('.hasPopover'));
            		var popoverList = popoverTriggerList.map(function(popoverTriggerEl){
		     		return new bootstrap.Popover(popoverTriggerEl, {
					html: true,
					container: 'body',
					placement: 'right',
					trigger: 'hover focus'
				});
			});
            	}
JS;

        wp_add_inline_script('jch-bootstrap-js', $popoverJs);

        if (JCH_PRO) {
            wp_register_style('jch-progressbar-css', JCH_PLUGIN_URL . 'media/jquery-ui/jquery-ui.css', [], JCH_VERSION);

            wp_register_script('jch-optimizeimage-js', JCH_PLUGIN_URL . 'media/core/js/optimize-image.js', [
                'jquery',
                'jch-adminutility-js',
                'jch-platformwordpress-js',
                'jch-bootstrap-js'
            ], JCH_VERSION, true);
            wp_register_script(
                'jch-progressbar-js',
                JCH_PLUGIN_URL . 'media/jquery-ui/jquery-ui.js',
                ['jquery'],
                JCH_VERSION,
                true
            );
            wp_register_script('jch-smartcombine-js', JCH_PLUGIN_URL . 'media/core/js/smart-combine.js', [
                'jquery',
                'jch-adminutility-js',
                'jch-platformwordpress-js'
            ], JCH_VERSION, true);
        }

        /** @psalm-var array<string, array<string, array{0:string, 1:string, 2?:bool}>> $aSettingsArray */
        $aSettingsArray = TabSettings::getSettingsArray();

        foreach ($aSettingsArray as $section => $aSettings) {
            add_settings_section('jch-optimize_' . $section . '_section', '', [
                '\\JchOptimize\\Html\\Renderer\\Section',
                $section
            ], 'jchOptimizeOptionsPage');


            foreach ($aSettings as $setting => $args) {
                list($title, $description, $new) = array_pad($args, 3, false);

                $id = 'jch-optimize_' . $setting;
                $title = Helper::description($title, $description, $new);
                $args = [];

                $aClasses = $this->getSettingsClassMap();

                if (isset($aClasses[$setting])) {
                    $args['class'] = $aClasses[$setting];
                }

                add_settings_field($id, $title, [
                    '\\JchOptimize\\Html\\Renderer\\Setting',
                    $setting
                ], 'jchOptimizeOptionsPage', 'jch-optimize_' . $section . '_section', $args);
            }
        }
    }

    /**
     * Map of classes that should be put on the '<tr>' element containing the associated setting
     *
     * @return array<string, string>
     */
    private function getSettingsClassMap(): array
    {
        return [
            'pro_http2_file_types'     => 'jch-wp-checkboxes-grid-wrapper columns-4',
            'staticfiles'              => 'jch-wp-checkboxes-grid-wrapper columns-5',
            'pro_staticfiles_2'        => 'jch-wp-checkboxes-grid-wrapper columns-5',
            'pro_staticfiles_3'        => 'jch-wp-checkboxes-grid-wrapper columns-5',
            'pro_html_sections'        => 'jch-wp-checkboxes-grid-wrapper columns-5 width-400',
            'memcached_server_host'    => 'jch-memcached-wrapper d-none',
            'memcached_server_port'    => 'jch-memcached-wrapper d-none',
            'redis_server_host'        => 'jch-redis-wrapper d-none',
            'redis_server_port'        => 'jch-redis-wrapper d-none',
            'redis_server_password'    => 'jch-redis-wrapper d-none',
            'redis_server_database'    => 'jch-redis-wrapper d-none',
            'pro_smart_combine_values' => 'd-none'
        ];
    }

    public function addMenuToAdminBar(WP_Admin_Bar $admin_bar): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $nodes = $admin_bar->get_nodes();

        if (!isset($nodes['jch-optimize-parent'])) {
            $admin_bar->add_node([
                'id'    => 'jch-optimize-parent',
                'title' => 'JCH Optimize'
            ]);
        }

        $admin_bar->add_node([
            'parent' => 'jch-optimize-parent',
            'id'     => 'jch-optimize-settings',
            'title'  => __('Settings', 'jch-optimize'),
            'href'   => add_query_arg([
                'page' => 'jch_optimize',
            ], admin_url('options-general.php'))
        ]);

        $admin_bar->add_node([
            'parent' => 'jch-optimize-parent',
            'id'     => 'jch-optimize-cache',
            'title'  => __('Clean Cache', 'jch-optimize'),
            'href'   => add_query_arg([
                'page'   => 'jch_optimize',
                'task'   => 'cleancache',
                'return' => base64_encode_url($this->getCurrentAdminUri())
            ], admin_url('options-general.php'))
        ]);
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

    /**
     * @return never-return
     */
    public function doAjaxFileTree()
    {
        check_admin_referer('jch_optimize_filetree');

        if (current_user_can('manage_options')) {
            echo Ajax::getInstance('FileTree')->run();
        }

        die();
    }

    /**
     * @return never-return
     */
    public function doAjaxMultiSelect()
    {
        check_admin_referer('jch_optimize_multiselect');

        if (current_user_can('manage_options')) {
            echo Ajax::getInstance('MultiSelect')->run();
        }

        die();
    }

    /**
     * @return never-return
     */
    public function doAjaxOptimizeImages()
    {
        check_admin_referer('jch_optimize_image');

        if (current_user_can('manage_options')) {
            AdminAjax::getInstance('OptimizeImage')->run();
        }

        die();
    }

    /**
     * @return never-return
     */
    public function doAjaxConfigureSettings()
    {
        if (current_user_can('manage_options')) {
            $container = ContainerFactory::getContainer();
            $container->get(ControllerResolver::class)->resolve();
        }

        die();
    }

    /**
     * @return never-return
     */
    public function doAjaxOnClickIcon()
    {
        if (current_user_can('manage_options')) {
            $container = ContainerFactory::getContainer();
            check_admin_referer($container->get(Input::class)->get('task'));
            $container->get(ControllerResolver::class)->resolve();
        }

        die();
    }

    /**
     * @return never-return
     */
    public function doAjaxSmartCombine()
    {
        if (current_user_can('manage_options')) {
            echo Ajax::getInstance('SmartCombine')->run();
        }

        die();
    }

    /**
     * @return never-return
     */
    public function doAjaxGetCacheInfo()
    {
        $container = ContainerFactory::getContainer();
        $container->get(Input::class)->def('task', 'getcacheinfo');
        $container->get(ControllerResolver::class)->resolve();

        die();
    }
}
