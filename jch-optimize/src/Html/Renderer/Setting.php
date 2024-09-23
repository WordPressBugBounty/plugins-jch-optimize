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

namespace JchOptimize\Html\Renderer;

use JchOptimize\Html\CacheStorageSupportHelper;
use JchOptimize\Html\Helper;

use function __;
use function array_keys;

abstract class Setting
{
    ## General Tab

    /*
    General Section
    */

    public static function pro_downloadid(): void
    {
        Helper::_('text.pro', __FUNCTION__, '');
    }

    public static function debug(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    public static function order_plugin(): void
    {
        Helper::_('radio', __FUNCTION__, '1');
    }

    public static function disable_logged_in_users(): void
    {
        Helper::_('radio', __FUNCTION__, '1');
    }

    public static function elements_above_fold(): void
    {
        Helper::_('text', __FUNCTION__, '300');
    }

    public static function elements_above_fold_marker(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    /*
    Exclude Menu Urls Section
    */

    public static function menuexcludedurl(): void
    {
        Helper::_('multiselect', __FUNCTION__, [], 'url', 'file');
    }

    /*
    Cache Storage Section
    */

    public static function pro_cache_storage_adapter(): void
    {
        $options = [
            'filesystem' => __('Filesystem', 'jch-optimize'),
            'apcu'       => __('APCu', 'jch-optimize'),
            'memcached'  => __('Memcached', 'jch-optimize'),
            'redis'      => __('Redis', 'jch-optimize')
        ];

        $class = CacheStorageSupportHelper::class;

        $conditions = [
            'apcu'      => [$class, 'isApcuSupported'],
            'memcached' => [$class, 'isMemcachedSupported'],
            'redis'     => [$class, 'isRedisSupported']
        ];

        Helper::_('select.pro', __FUNCTION__, 'filesystem', $options, '', $conditions);
    }

    public static function cache_lifetime(): void
    {
        $aOptions = [
            '1800'    => __('30 min', 'jch-optimize'),
            '3600'    => __('1 hour', 'jch-optimize'),
            '10800'   => __('3 hours', 'jch-optimize'),
            '21600'   => __('6 hours', 'jch-optimize'),
            '43200'   => __('12 hours', 'jch-optimize'),
            '86400'   => __('1 day', 'jch-optimize'),
            '172800'  => __('2 days', 'jch-optimize'),
            '604800'  => __('7 days', 'jch-optimize'),
            '1209600' => __('2 weeks', 'jch-optimize')
        ];

        Helper::_('select', __FUNCTION__, '1800', $aOptions);
    }

    public static function memcached_server_host(): void
    {
        Helper::_('text.pro', __FUNCTION__, '127.0.0.1');
    }

    public static function memcached_server_port(): void
    {
        Helper::_('text.pro', __FUNCTION__, '11211');
    }

    public static function redis_server_host(): void
    {
        Helper::_('text.pro', __FUNCTION__, '127.0.0.1');
    }

    public static function redis_server_port(): void
    {
        Helper::_('text.pro', __FUNCTION__, '6379');
    }

    public static function redis_server_password(): void
    {
        Helper::_('input.pro', __FUNCTION__, '', 'password');
    }

    public static function redis_server_database(): void
    {
        Helper::_('text.pro', __FUNCTION__, '0');
    }

    public static function delete_expiry(): void
    {
        Helper::_('radio', __FUNCTION__, '1');
    }

    ## Combine Files tab

    /*
    Combine CSS/Js Section
    */

    public static function combine_files_enable(): void
    {
        Helper::_('radio', __FUNCTION__, '1');
    }

    public static function pro_smart_combine(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '0', 'jch-smart-combine-radios-wrapper');
    }

    public static function html_minify_level(): void
    {
        $aOptions = [
            '0' => __('Basic', 'jch-optimize'),
            '1' => __('Advanced', 'jch-optimize'),
            '2' => __('Ultra', 'jch-optimize')
        ];

        Helper::_('select', __FUNCTION__, '0', $aOptions);
    }

    public static function htaccess(): void
    {
        $aOptions = [
            '2' => __('Static css and js files', 'jch-optimize'),
            '0' => __('PHP file with query', 'jch-optimize'),
            '1' => __('PHP using url re-write', 'jch-optimize'),
            '3' => __('PHP using url re-write (Without Options +FollowSymLinks)', 'jch-optimize'),
        ];

        Helper::_('select', __FUNCTION__, '2', $aOptions);
    }

    public static function try_catch(): void
    {
        Helper::_('radio', __FUNCTION__, '1');
    }

    /*
    Combine Files Automatic Section
    */

    public static function gzip(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    public static function html_minify(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    public static function includeAllExtensions(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    public static function phpAndExternal(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    ## CSS Tab

    /*
    CSS Automatic Settings Section
    */

    public static function css(): void
    {
        Helper::_('radio', __FUNCTION__, '1');
    }

    public static function css_minify(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    public static function replaceImports(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    public static function inlineStyle(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    /*
    Exclude CSS Files section
    */

    public static function excludeCss(): void
    {
        Helper::_('multiselect', __FUNCTION__, [], 'css', 'file');
    }

    public static function excludeCssComponents(): void
    {
        Helper::_('multiselect', __FUNCTION__, [], 'css', 'extension');
    }

    public static function excludeStyles(): void
    {
        Helper::_('multiselect', __FUNCTION__, [], 'css', 'style');
    }

    public static function excludeAllStyles(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    /*
    Remove CSS Files section
    */

    public static function remove_css(): void
    {
        Helper::_('multiselect', __FUNCTION__, [], 'css', 'file');
    }

    /*
     Custom CSS section
     */
    public static function mobile_css(): void
    {
        Helper::_('textarea', __FUNCTION__, '');
    }

    public static function desktop_css(): void
    {
        Helper::_('textarea', __FUNCTION__, '');
    }

    /*
    Optimize CSS Delivery Section
    */

    public static function optimizeCssDelivery_enable(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    public static function pro_reduce_unused_css(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '0');
    }

    public static function pro_dynamic_selectors(): void
    {
        Helper::_('multiselect.pro', __FUNCTION__, [], 'selectors', 'style');
    }

    ## JavaScript Tab

    /*
    JavaScript Automatic Settings Section
    */

    public static function javascript(): void
    {
        Helper::_('radio', __FUNCTION__, '1');
    }

    public static function js_minify(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    public static function inlineScripts(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    public static function bottom_js(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    public static function loadAsynchronous(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    /*
    Exclude JavaScript Files
    */

    public static function excludeJs_peo(): void
    {
        Helper::_('multiselectjs', __FUNCTION__, [], 'js', 'file', 'url');
    }

    public static function excludeJsComponents_peo(): void
    {
        Helper::_('multiselectjs', __FUNCTION__, [], 'js', 'extension', 'url');
    }

    public static function excludeScripts_peo(): void
    {
        Helper::_('multiselectjs', __FUNCTION__, [], 'js', 'script', 'script');
    }

    public static function excludeAllScripts(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    /*
    Remove JavaScript Files Section
     */
    public static function remove_js(): void
    {
        Helper::_('multiselect', __FUNCTION__, [], 'js', 'file');
    }

    /*
    Reduce Unused JavaScript Section
    */

    public static function pro_reduce_unused_js_enable(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '0');
    }

    public static function pro_criticalJs(): void
    {
        Helper::_('multiselect.pro', __FUNCTION__, [], 'criticaljs', 'file');
    }

    public static function pro_criticalScripts(): void
    {
        Helper::_('multiselect.pro', __FUNCTION__, [], 'js', 'script');
    }

    public static function pro_criticalModules(): void
    {
        Helper::_('multiselect.pro', __FUNCTION__, [], 'modules', 'file');
    }

    public static function pro_criticalModulesScripts(): void
    {
        Helper::_('multiselect.pro', __FUNCTION__, [], 'js', 'script');
    }

    public static function pro_defer_criticalJs(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '1');
    }


    ## Page Cache Tab

    /*
    Page Cache Section
    */

    public static function cache_enable(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    public static function pro_cache_platform(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '0');
    }

    public static function page_cache_exclude_form_users(): void
    {
        Helper::_('radio', __FUNCTION__, '1');
    }

    public static function page_cache_lifetime(): void
    {
        $aOptions = [
            '900'    => __('15 min', 'jch-optimize'),
            '1800'   => __('30 min', 'jch-optimize'),
            '3600'   => __('1 hour', 'jch-optimize'),
            '10800'  => __('3 hours', 'jch-optimize'),
            '21600'  => __('6 hours', 'jch-optimize'),
            '43200'  => __('12 hours', 'jch-optimize'),
            '86400'  => __('1 day', 'jch-optimize'),
            '172800' => __('2 days', 'jch-optimize'),
            '604800' => __('1 week', 'jch-optimize')
        ];

        Helper::_('select', __FUNCTION__, '900', $aOptions);
    }

    public static function cache_exclude(): void
    {
        //We're using class as the last argument to be able to include '?' in the multiselect box
        Helper::_('multiselect', __FUNCTION__, [], 'url', 'class');
    }

    public static function page_cache_ignore_query_values(): void
    {
        Helper::_('multiselect', __FUNCTION__, [], 'url', 'class');
    }

    public static function pro_capture_cache_enable(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '0');
    }

    ## Media Tab

    /*
    Add Image Attributes Section
    */

    public static function img_attributes_enable(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    /*
    Sprite Generator Section
    */

    public static function csg_enable(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    public static function csg_direction(): void
    {
        $aOptions = [
            'vertical'   => __('vertical', 'jch-optimize'),
            'horizontal' => __('horizontal', 'jch-optimize')
        ];

        Helper::_('select', __FUNCTION__, 'vertical', $aOptions);
    }

    public static function csg_wrap_images(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    public static function csg_exclude_images(): void
    {
        Helper::_('multiselect', __FUNCTION__, [], 'images', 'file');
    }

    public static function csg_include_images(): void
    {
        Helper::_('multiselect', __FUNCTION__, [], 'images', 'file');
    }

    /*
    Lazy Load Images Section
    */

    public static function lazyload_enable(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    public static function lazyload_autosize(): void
    {
        Helper::_('radio', __FUNCTION__, '1');
    }

    public static function pro_lazyload_effects(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '0');
    }

    public static function pro_lazyload_iframe(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '0');
    }

    public static function pro_lazyload_audiovideo(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '0');
    }

    public static function pro_lazyload_bgimages(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '0');
    }

    public static function excludeLazyLoad(): void
    {
        Helper::_('multiselect', __FUNCTION__, [], 'lazyload', 'file');
    }

    public static function pro_excludeLazyLoadFolders(): void
    {
        Helper::_('multiselect.pro', __FUNCTION__, [], 'lazyload', 'folder');
    }

    public static function pro_excludeLazyLoadClass(): void
    {
        Helper::_('multiselect.pro', __FUNCTION__, [], 'lazyload', 'class');
    }

    ## Preloads Tab

    /*
    HTTP/2 Preload
    */

    public static function http2_push_enable(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    public static function pro_http2_preload_modules(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '1');
    }

    public static function pro_http2_file_types(): void
    {
        $aOptions = [
            'style'  => 'style',
            'script' => 'script',
            'font'   => 'font',
            'image'  => 'image'
        ];

        Helper::_('checkboxes', __FUNCTION__, array_keys($aOptions), $aOptions);
    }

    public static function pro_http2_include(): void
    {
        Helper::_('multiselect', __FUNCTION__, [], 'http2', 'file');
    }

    public static function pro_http2_exclude(): void
    {
        Helper::_('multiselect', __FUNCTION__, [], 'http2', 'file');
    }

    /*
     Largest Contentful Paint Images
     */

    public static function pro_lcp_images_enable(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '0');
    }

    public static function pro_lcp_images(): void
    {
        Helper::_('multiselect.pro', __FUNCTION__, [], 'lazyload', 'file');
    }

    /*
    Optimize Fonts Section
    */

    public static function pro_optimizeFonts_enable(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '0');
    }

    public static function pro_force_swap_policy(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '1');
    }

    public static function pro_optimize_font_files(): void
    {
        Helper::_('multiselect.pro', __FUNCTION__, [], 'css', 'file');
    }

    /*
     Preconnect Third-party
     */

    public static function pro_preconnect_domains_enable(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '0');
    }

    public static function pro_preconnect_domains(): void
    {
        Helper::_('multiselect.pro', __FUNCTION__, [], 'url', 'file');
    }

    ## CDN Tab

    /*
    CDN Section
    */

    public static function cookielessdomain_enable(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    public static function cdn_scheme(): void
    {
        $aOptions = [
            '0' => __('scheme relative', 'jch-optimize'),
            '1' => __('http', 'jch-optimize'),
            '2' => __('https', 'jch-optimize')
        ];

        Helper::_('select', __FUNCTION__, '0', $aOptions);
    }

    public static function cookielessdomain(): void
    {
        Helper::_('text', __FUNCTION__, '');
    }

    public static function staticfiles(): void
    {
        Helper::_('checkboxes', __FUNCTION__, array_keys(self::staticFilesArray()), self::staticFilesArray());
    }

    /**
     * @return array<string, string>
     */
    private static function staticFilesArray(): array
    {
        return [
            'css'   => 'css',
            'png'   => 'png',
            'gif'   => 'gif',
            'ico'   => 'ico',
            'pdf'   => 'pdf',
            'js'    => 'js',
            'jpe?g' => 'jp(e)g',
            'bmp'   => 'bmp',
            'webp'  => 'webp',
            'svg'   => 'svg'
        ];
    }

    public static function pro_customcdnextensions(): void
    {
        Helper::_('multiselect.pro', __FUNCTION__, [], 'url', 'file');
    }

    public static function pro_cookielessdomain_2(): void
    {
        Helper::_('text.pro', __FUNCTION__, '');
    }

    public static function pro_staticfiles_2(): void
    {
        Helper::_('checkboxes.pro', __FUNCTION__, array_keys(self::staticFilesArray()), self::staticFilesArray());
    }

    public static function pro_cookielessdomain_3(): void
    {
        Helper::_('text.pro', __FUNCTION__, '');
    }

    public static function pro_staticfiles_3(): void
    {
        Helper::_('checkboxes.pro', __FUNCTION__, array_keys(self::staticFilesArray()), self::staticFilesArray());
    }

    ## Optimize Images Tab

    /*
    Global Options Section
    */

    public static function ignore_optimized(): void
    {
        Helper::_('radio', __FUNCTION__, '1');
    }

    public static function pro_next_gen_images(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '1');
    }

    public static function pro_web_old_browsers(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '0');
    }

    public static function pro_load_webp_images(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '0');
    }

    public static function pro_gen_responsive_images(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '1');
    }

    public static function pro_load_responsive_images(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '0');
    }

    public static function lossy(): void
    {
        Helper::_('radio', __FUNCTION__, '1');
    }

    public static function save_metadata(): void
    {
        Helper::_('radio', __FUNCTION__, '0');
    }

    /*
    Optimize Images By URLs Section
    */

    public static function pro_api_resize_mode(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '1');
    }

    /*
    Optimize Images By Folders Section
    */

    public static function recursive(): void
    {
        Helper::_('radio', __FUNCTION__, '1');
    }

    ## Miscellaneous Tab

    /*
    Reduce DOM Section
    */

    public static function pro_reduce_dom(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '0');
    }

    public static function pro_html_sections(): void
    {
        $options = [
            'section' => 'section',
            'header'  => 'header',
            'footer'  => 'footer',
            'aside'   => 'aside',
            'nav'     => 'nav'
        ];

        Helper::_('checkboxes.pro', __FUNCTION__, array_keys($options), $options);
    }

    /*
    Mode Switcher Menu Section
    */

    public static function pro_disableModeSwitcher(): void
    {
        Helper::_('radio.pro', __FUNCTION__, '0');
    }
}
