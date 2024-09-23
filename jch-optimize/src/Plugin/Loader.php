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
use JchOptimize\Core\Container\ContainerAwareInterface;
use JchOptimize\Core\Container\ContainerAwareTrait;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Input;
use JchOptimize\Core\Optimize;
use JchOptimize\Core\PageCache\PageCache;
use JchOptimize\Core\Psr\Log\LoggerAwareInterface;
use JchOptimize\Core\Psr\Log\LoggerAwareTrait;
use JchOptimize\Core\Psr\Log\LogLevel;
use JchOptimize\Core\Registry;
use JchOptimize\Core\SystemUri;
use JchOptimize\Platform\Utility;
use WP_Admin_Bar;

use function add_action;
use function add_filter;
use function apply_filters;
use function current_user_can;
use function defined;
use function delete_option;
use function get_option;
use function is_admin;
use function load_plugin_textdomain;
use function plugin_basename;
use function register_activation_hook;
use function register_uninstall_hook;
use function update_option;
use function wp_is_mobile;

use const DOING_AJAX;
use const JCH_PRO;
use const SHORTINIT;
use const WP_USE_THEMES;
use const WPMU_PLUGIN_DIR;

class Loader implements LoggerAwareInterface, ContainerAwareInterface
{
    use LoggerAwareTrait;
    use ContainerAwareTrait;

    /**
     * @var Registry
     */
    private Registry $params;
    /**
     * @var Admin
     */
    private Admin $admin;
    /**
     * @var PageCache
     */
    private PageCache $pageCache;
    /**
     * @var Installer
     */
    private Installer $installer;
    /**
     * @var Updater|null
     */
    private ?Updater $updater;
    /**
     * @var Optimize
     */
    private Optimize $optimize;

    public function __construct(
        Registry  $params,
        Admin     $admin,
        Installer $installer,
        ?Updater  $updater,
        Optimize  $optimize,
        PageCache $pageCache
    ) {
        $this->params = $params;
        $this->admin = $admin;
        $this->installer = $installer;
        $this->updater = $updater;
        $this->optimize = $optimize;
        $this->pageCache = $pageCache;
    }

    /**
     * Function register_uninstall_hook can only be used with a static method
     *
     * @return void
     */
    public static function runUninstallRoutines()
    {
        /** @var Installer $installer */
        $installer = ContainerFactory::getContainer()->get(Installer::class);
        $installer->deactivate();
    }

    public function preboot_init(): void
    {
        if (false !== ($settings = get_option('jch_options'))) {
            update_option('jch-optimize_settings', $settings);
            delete_option('jch_options');
        }
    }

    public function init(): void
    {
        $this->runActivationRoutines();

        if (is_admin()) {

            if (defined('DOING_AJAX')) { //Ajax functions
                add_action('wp_ajax_filetree', [$this->admin, 'doAjaxFileTree']);
                add_action('wp_ajax_multiselect', [$this->admin, 'doAjaxMultiSelect']);
                add_action('wp_ajax_optimizeimages', [$this->admin, 'doAjaxOptimizeImages']);
                add_action('wp_ajax_smartcombine', [$this->admin, 'doAjaxSmartCombine']);
                add_action('wp_ajax_configuresettings', [$this->admin, 'doAjaxConfigureSettings']);
                add_action('wp_ajax_getcacheinfo', [$this->admin, 'doAjaxGetCacheInfo']);
                add_action('wp_ajax_onclickicon', [$this->admin, 'doAjaxOnClickIcon']);
            } else {
                add_action('admin_menu', [$this->admin, 'addAdminMenu']);
                add_action('admin_init', [$this->admin, 'registerOptions']);
                add_filter('plugin_action_links', [$this->admin, 'loadActionLinks'], 10, 2);
                $this->installer->updateSettings();
                $this->installer->fixMetaFileSecurity();
            }
        } else {
            $url_exclude = (array) $this->params->get('menuexcludedurl', []);
            /** @var Input $input */
            $input = $this->container->get(Input::class);
            $jch_backend = (string) $input->get('jchbackend');
            /** @var string|null $nooptimize */
            $nooptimize = $input->get('jchnooptimize');

            if (defined('WP_USE_THEMES')
                && WP_USE_THEMES
                && $jch_backend != '1'
                && is_null($nooptimize)
                && version_compare(PHP_VERSION, '7.4.0', '>=')
                && !defined('DOING_AJAX')
                && !defined('DOING_CRON')
                && !defined('APP_REQUEST')
                && !defined('XMLRPC_REQUEST')
                && (!defined('SHORTINIT') || (defined('SHORTINIT') && !SHORTINIT))
                && !Helper::findExcludes($url_exclude, SystemUri::toString())
                && $input->server->getString('HTTP_SEC_FETCH_DEST') != 'iframe') {
                //Disable NextGen Resource Manager; incompatible with plugin
                //add_filter( 'run_ngg_resource_manager', '__return_false' );
                add_action('init', [$this, 'initializeCache'], 0);

                ob_start([$this, 'runOptimize']);

                if (JCH_DEBUG && !$this->params->get('disable_logged_in_users', '1')) {
                    add_action('admin_bar_menu', [$this, 'addMenuToAdminBar'], 101);
                }
            }
        }

        add_action('plugins_loaded', [$this, 'loadPluginTextDomain']);
        //Function register_uninstall_hook can only be used with a static class method
        register_uninstall_hook(JCH_PLUGIN_FILE, [__CLASS__, 'runUninstallRoutines']);

        if ($this->params->get('order_plugin', '1')) {
            add_action('activated_plugin', [$this, 'orderPlugin']);
            add_action('deactivated_plugin', [$this, 'orderPlugin']);
        }

        if (JCH_PRO) {
            $this->loadProUpdater();

            if ($this->params->get('pro_cache_platform', '0')) {
                add_filter(
                    'jch_optimize_get_page_cache_id',
                    [
                        $this,
                        'getPageCacheHash'
                    ],
                    10,
                    2
                );
            }
        }
    }

    public function runActivationRoutines(): void
    {
        //Handles activation routines
        register_activation_hook(JCH_PLUGIN_FILE, [$this->installer, 'activate']);

        $mu_folder = ABSPATH . 'wp-content/mu-plugins';

        if (defined('WPMU_PLUGIN_DIR') && WPMU_PLUGIN_DIR) {
            $mu_folder = WPMU_PLUGIN_DIR;
        }

        if (!file_exists(JCH_PLUGIN_DIR . 'dir.php')
            || (JCH_PRO && is_admin()
                && !file_exists($mu_folder . '/jch-optimize-mode-switcher.php'))) {
            $this->installer->activate();
        }
    }

    protected function loadProUpdater(): void
    {
        $this->updater->load();
    }

    public function initializeCache(): void
    {
        $this->pageCache->initialize();
        $this->pageCache->setCaching();
    }

    public function runOptimize(string $html): string
    {
        if (!Helper::validateHtml($html)) {
            return $html;
        }

        //need to check this here, it could be set dynamically
        global $jch_no_optimize;

        /** @var string $disable_logged_in */
        $disable_logged_in = $this->params->get('disable_logged_in_users', '1');

        //Need to call Utility::isGuest after init has been called
        if ($jch_no_optimize || ($disable_logged_in && !Utility::isGuest())) {
            return $html;
        }

        try {
            $this->optimize->setHtml($html);

            $optimizedHtml = $this->optimize->process();

            //required for compatibility with Hide My WP Ghost https://wordpress.org/support/topic/compatibility-with-hide-my-wp-ghost/
            /** @var string $optimizedHtml */
            $optimizedHtml = apply_filters('jch_optimize_save_content', $optimizedHtml);

            $this->pageCache->store($optimizedHtml);
        } catch (Exception $e) {
            $this->logger->log(LogLevel::ERROR, (string)$e);

            $optimizedHtml = $html;
        }

        return $optimizedHtml;
    }

    public function loadPluginTextDomain(): void
    {
        load_plugin_textdomain('jch-optimize', false, dirname(plugin_basename(JCH_PLUGIN_FILE)) . '/languages');
    }

    public function orderPlugin(): bool
    {
        $active_plugins = (array)get_option('active_plugins', []);
        $order = [
            'jch-optimize/jch-optimize.php',
        ];

        //Get the plugins in $order that are currently activated
        $order_short_list = array_intersect($order, $active_plugins);
        //Remove plugins in $order_short_list from list of activated plugins
        $active_plugins_slist = array_diff($active_plugins, $order_short_list);
        //Merge $order with $active_plugins_list
        $ordered_active_plugins = array_merge($order_short_list, $active_plugins_slist);

        update_option('active_plugins', $ordered_active_plugins);

        return true;
    }

    public function getPageCacheHash(array $parts): array
    {
        if (wp_is_mobile()) {
            $parts[] = '_MOBILE_';
        }

        return $parts;
    }

    public function addMenuToAdminBar(WP_Admin_Bar $admin_bar): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $nodes = $admin_bar->get_nodes();

        if (!isset($nodes['jch-optimize-parent'])) {
            $aArgs = [
                'id' => 'jch-optimize-parent',
                'title' => __('JCH Optimize', 'jch-optimize')
            ];

            $admin_bar->add_node($aArgs);
        }

        $aArgs = [
            'parent' => 'jch-optimize-parent',
            'id' => 'jch-optimize-profiler',
            'title' => __('Profiler', 'jch-optimize'),
        ];

        $admin_bar->add_node($aArgs);
    }
}
