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

namespace JchOptimize\Html;

use function __;
use function array_merge;
use function get_class_methods;
use function sprintf;
use function str_replace;

/**
 * Helper class to feed settings information to JchOptimizeAdmin
 *
 * Each method returns an array of sections of settings for each tab. Each setting contains an indexed array of the following information
 * setting => ['title', 'description', 'new']
 *
 */
abstract class TabSettings
{
    public static function getSettingsArray(): array
    {
        $aTabs = get_class_methods(__CLASS__);
        $aSettingsArray = [];

        foreach ($aTabs as $tab) {
            if ($tab == str_replace(__CLASS__ . '::', '', __METHOD__)) {
                continue;
            }

            $aSettingsArray = array_merge($aSettingsArray, self::$tab());
        }

        return $aSettingsArray;
    }

    public static function generalTab(): array
    {
        return [
            /**
             * Miscellaneous
             */
            'miscellaneousSection'    => [
                'pro_downloadid'             => [
                    __('Download ID', 'jch-optimize'),
                    __(
                        'You\'ll find your Download ID in your account. Enter your Download ID here to enable automatic updates of the PRO version and to access our Optimize Image API.',
                        'jch-optimize'
                    ),
                    true
                ],
                'debug'                      => [
                    __('Debug Plugin', 'jch-optimize'),
                    __(
                        'This option will add the \'commented out\' url of the individual files inside the combined file above the contents that came from that file. This is useful when configuring the plugin and trying to resolve conflicts. <p>This will also add a Profiler menu to the AdminBar, so you can review the times that the plugin methods take to run.',
                        'jch-optimize'
                    )
                ],
                'order_plugin'               => [
                    __('Order plugin', 'jch-optimize'),
                    __(
                        'If enabled, the plugin will ensure that the correct execution order of plugins to maintain compatibility with popular caching plugins are restored whenever a plugin is activated or deactivated.'
                    )
                ],
                'disable_logged_in_users'    => [
                    __('Disable logged in users', 'jch-optimize'),
                    __(
                        'When enabled, the plugin will be disabled for all users that are logged in',
                        'jch-optimize'
                    )
                ],
                'elements_above_fold'        => [
                    __('Elements above fold', 'jch-optimize'),
                    __(
                        'This is used by the Optimize CSS Delivery, Lazy Load, and Reduce DOM features. Enter the value that represents the amount of HTML elements above the fold so these features can find the critical CSS for the section above the fold, and lazy load images and HTML sections below the fold respectively.'
                    )
                ],
                'elements_above_fold_marker' => [
                    __('Above fold marker', 'jch-optimize'),
                    __(
                        'DO NOT LEAVE THIS ENABLED! This only works if Debug Plugin above is enabled. This prints a big bright red dot where the plugin has calculated that the \'above the fold\' line is. Gradually increase the \'Elements above fold\' value until the dot falls below the fold on mobile and desktop.'
                    )
                ]
            ],
            /*
             * Exclude Menu Items
             */
            'excludeMenuItemsSection' => [
                'menuexcludedurl' => [
                    __('Exclude urls', 'jch-optimize'),
                    __(
                        'Enter a substring of the url you want to exclude here. Just type the string in the textbox then click the \'Add Item\' button for each url then save your settings',
                        'jch-optimize'
                    )
                ]
            ],
            /**
             * Cache Storage Settings
             */
            'cacheStorageSection'     => [
                'pro_cache_storage_adapter' => [
                    __('Storage Adapter', 'jch-optimize'),
                    __(
                        '<p>Select the type of storage you want to use for caching. Available options are: </p><ul><li>Filesystem</li><li>Memcached</li><li>APCu</li><li>Redis</li><li>WinCache</li></ul><p>The default is filesystem and any storage not supported by your system will be disabled. </p> '
                    )
                ],
                'cache_lifetime'            => [
                    __('Cache lifetime', 'jch-optimize'),
                    __(
                        'The lifetime of the cache files generated by the plugin. <p>If you\'re using a Page Cache plugin be sure to set this higher than the lifetime set in Page Cache. <p>If you\'re having issue with excess amount of cache being generated then lowering this setting will help.',
                        'jch-optimize'
                    )
                ],
                'memcached_server_host'     => [
                    __('Memcached server host', 'jch-optimize'),
                    __(
                        'Enter the host of the Memcached server you want to use, e.g, \'127.0.0.1\'',
                        'jch-optimize'
                    )
                ],
                'memcached_server_port'     => [
                    __('Memcached server port', 'jch-optimize'),
                    __(
                        'Enter the port number of the Memcached server you have configured, e.g \'11211\'',
                        'jch-optimize'
                    )
                ],
                'redis_server_host'         => [
                    __('Redis server host', 'jch-optimize'),
                    __('Set the Redis server host, e.g., \'127.0.0.1\'', 'jch-optimize')
                ],
                'redis_server_port'         => [
                    __('Redis server port', 'jch-optimize'),
                    __('Set the Redis server port, e.g., \'6379\'', 'jch-optimize')
                ],
                'redis_server_password'     => [
                    __('Redis server password', 'jch-optimize'),
                    __('Set the Redis server password if there\'s one configured', 'jch-optimize')
                ],
                'redis_server_database'     => [
                    __('Redis database', 'jch-optimize'),
                    __('Set the database identifier', '0')
                ],
                'delete_expiry'             => [
                    __('Delete expired cache', 'jch-optimize'),
                    __(
                        'JCH Optimize will delete cached files when they\'re expired to prevent an excess build-up of cache files on the server. <p> If you\'re using another page cache plugin, you may want to disable this to avoid having orphaned links to CSS or JavaScript files cached on the pages.'
                    )
                ],
            ],

        ];
    }

    public static function combineFilesTab(): array
    {
        return [
            /**
             * Combine CSS JS
             */
            'combineCssJsSection'     => [
                'combine_files_enable' => [
                    __('Enable', 'jch-optimize'),
                ],
                'pro_smart_combine'    => [
                    __('Smart combine', 'jch-optimize'),
                    __(
                        'Will try to combine system and template files together respectively that are consistent across pages. <p>This produces several smaller combined files promoting browser caching across page loads; reduces chances of excessive cache generation and takes advantages of better files delivery offered by Http/2 servers',
                        'jch-optimize'
                    )
                ],
                'html_minify_level'    => [
                    __('HTML Minification level', 'jch-optimize'),
                    __(
                        'If \'Minify HTML\' is enabled, this will determine the level of minification. The incremental changes per level are as follows: <dl><dt>Basic - </dt><dd>Adjoining whitespaces outside of elements are reduced to one whitespace, HTML comments preserved;</dd><dt>Advanced - </dt><dd>Remove HTML comments, whitespace around block elements and undisplayed elements, Remove unnecessary whitespaces inside of elements and around their attributes;</dd> <dt>Ultra -</dt> <dd>Remove redundant attributes, for example, \'text/javascript\', and remove quotes from around selected attributes (HTML5)</dd> </dl>',
                        'jch-optimize'
                    )
                ],
                'htaccess'             => [
                    __('Combined files delivery', 'jch-optimize'),
                    __(
                        'By default, the combined files will be loaded as static CSS and JavaScript files. You would need to include directives in your .htaccess file to gzip these files. <p>You can use PHP files instead that will be gzipped if that option is set. PHP files can be loaded with a query attached with the information to find the combined files, or you can use url rewrite if it\'s available on the server so the files can be masked as static files. If your server prohibits the use of the Options +FollowSymLinks directive in .htaccess files use the respective option.',
                        'jch-optimize'
                    )
                ],
                'try_catch'            => [
                    __('Use try-catch', 'jch-optimize'),
                    __(
                        'If you\'re seeing JavaScript errors in the console, you can try enabling this option to wrap each JavaScript file in a \'try-catch\' block to prevent the errors from one file affecting the combined file.',
                        'jch-optimize'
                    )
                ]
            ],

            /**
             *Combine Css Js Auto Settings
             */
            'combineCssJsAutoSection' => [
                'gzip'                 => [
                    __('GZip JavaScript and CSS', 'jch-optimize'),
                    __(
                        'The plugin will compress the combined JavaScript and CSS file respectively using gzip, if you\'ve selected one of the PHP options for \'Combined files delivery\'. This can decrease file size dramatically.',
                        'jch-optimize'
                    )
                ],
                'html_minify'          => [
                    __('Minify HTML', 'jch-optimize'),
                    __(
                        'The plugin will remove all unnecessary whitespaces and comments from the HTML to reduce the total size of the web page.',
                        'jch-optimize'
                    )
                ],
                'includeAllExtensions' => [
                    __('Include all plugins', 'jch-optimize'),
                    __(
                        'By default, all files from third party plugins and external domains are excluded. If enabled, they will be included.',
                        'jch-optimize'
                    )
                ],
                'phpAndExternal'       => [
                    __('Include PHP and external files', 'jch-optimize'),
                    __(
                        'JavaScript and CSS files with \'.php\' file extensions, and files from external domains will be included in the combined file. <p> This option requires that either cURL is installed on your server or your php option \'allow_url_fopen\' is enabled.'
                    )
                ]
            ]
        ];
    }

    public static function cssTab(): array
    {
        return [
            /**
             * CSS Automatic Settings
             */
            'cssAutoSettingsSection'     => [
                'css'            => [
                    __('Combine CSS files', 'jch-optimize'),
                    __(
                        'This will combine all CSS files into one file and remove all the links to the individual files from the page, replacing them with a link generated by the plugin to the combined file.',
                        'jch-optimize'
                    )
                ],
                'css_minify'     => [
                    __('Minify CSS', 'jch-optimize'),
                    __(
                        'The plugin will remove all unnecessary whitespaces and comments from the combined CSS file to reduce the total file size.',
                        'jch-optimize'
                    )
                ],
                'replaceImports' => [
                    __('Replace @imports in CSS', 'jch-optimize'),
                    sprintf(
                        __(
                            'The plugin will replace %s at-rules with the contents of the files they are importing. This will be done recursively.',
                            'jch-optimize'
                        ),
                        '@import'
                    )
                ],
                'inlineStyle'    => [
                    __('Include in-page &lt;style&gt;\'s'),
                    sprintf(
                        __(
                            'In-page CSS inside %s tags will be included in the aggregated file in the order they appear on the page.',
                            'jch-optimize'
                        ),
                        '&lt;style$gt;'
                    )
                ]
            ],
            /**
             * Exclude CSS Settings
             */
            'excludeCssSection'          => [
                'excludeCSS'           => [
                    __('Exclude CSS files', 'jch-optimize'),
                    __('Select the CSS files you want to exclude.', 'jch-optimize')
                ],
                'excludeCssComponents' => [
                    __('Exclude CSS from these plugins', 'jch-optimize'),
                    __(
                        'The plugin will exclude all CSS files from the extensions you have selected.',
                        'jch-optimize'
                    )
                ],
                'excludeStyles'        => [
                    sprintf(__('Exclude individual in-page %s', 'jch-optimize'), '&lt;style&gt;\'s'),
                    sprintf(
                        __('Select the internal %s you want to exclude.', 'jch-optimize'),
                        '&lt;style&gt;'
                    )
                ],
                'excludeAllStyles'     => [
                    sprintf(__('Exclude all in-page %s', 'jch-optimize'), '&lt;style&gt;s'),
                    sprintf(
                        __(
                            'This is useful if you are generating an excess amount of cache files due to the file name of the combined CSS file keeps changing and you can\'t identify which %s declaration is responsible',
                            'jch-optimize'
                        ),
                        '&lt;style&gt;'
                    )
                ]
            ],
            /**
             * Remove CSS
             */
            'removeCssSection'           => [
                'remove_css' => [
                    __('Remove CSS Files', 'jch-optimize'),
                    __(
                        'select or add the files you want to prevent from loading on the site\'s pages.',
                        'jch-optimize'
                    )
                ]
            ],
            /**
             * Optimize CSS Delivery
             */
            'optimizeCssDeliverySection' => [
                'optimizeCssDelivery_enable' => [
                    __('Enable', 'jch-optimize'),
                ],
                'pro_reduce_unused_css'      => [
                    __('Reduce unused CSS', 'jch-optimize'),
                    __(
                        'When enabled, the plugin will \'lazy-load\' the combined CSS file only after the page is interacted with to prevent unnecessary processing of unused CSS before the page is loaded.',
                        'jch-optimize'
                    )
                ],
                'pro_dynamic_selectors'      => [
                    __('CSS dynamic selectors', 'jch-optimize'),
                    __(
                        'In some cases when Reduce Unused CSS is enabled, you may need to add the CSS for some dynamic elements to the critical CSS for them to load properly. Add any substring from the CSS declaration here to have them included.',
                        'jch-optimize'
                    )
                ]
            ],
            /**
             * Custom CSS
             */
            'customCssSection'           => [
                'mobile_css'  => [
                    __('Mobile', 'jch-optimize'),
                    __(
                        'Add simple CSS declarations to allocate space for elements rendering on mobile.',
                        'jch-optimize'
                    )
                ],
                'desktop_css' => [
                    __('Desktop', 'jch-optimize'),
                    __(
                        'Add CSS for preventing CLS issues on desktop devices here.',
                        'jch-optimize'
                    )
                ]
            ]
        ];
    }

    /**
     * Settings found on the JavaScript Tab
     *
     * @return array
     */

    public static function javascriptTab(): array
    {
        return [
            /**
             * JavaScript Automatic Settings
             */
            'javascriptAutomaticSettingsSection' => [
                'javascript'       => [
                    __('Combine JavaScript files', 'jch-optimize'),
                    __(
                        'This will combine all JavaScript files into one file and remove all the links to the individual files from the page, replacing them with a link generated by the plugin to the combined file.',
                        'jch-optimize'
                    )
                ],
                'js_minify'        => [
                    __('Minify JavaScript', 'jch-optimize'),
                    __(
                        'The plugin will remove all unnecessary whitespaces and comments from the combined JavaScript file to reduce the total file size.',
                        'jch-optimize'
                    )
                ],
                'inlineScripts'    => [
                    sprintf(__('Include in-page %s declarations', 'jch-optimize'), '&lt;script&gt;'),
                    sprintf(
                        __(
                            'In-page JavaScript inside %s tags will be included in the combined file in the order they appear on the page.',
                            'jch-optimize'
                        ),
                        '&lt;script&gt;'
                    )
                ],
                'bottom_js'        => [
                    __('Position JavaScript files at bottom of page', 'jch-optimize'),
                    sprintf(
                        __(
                            'Place combined JavaScript file at bottom of the page just before the ending %1$s tag.<p> If some JavaScript files are excluded while preserving execution order, only the last combined JavaScript file will be placed at the bottom of the page.<p> If this is disabled, the plugin only combines files found in the %2$s section of the HTML. This option extends the search to the %3$s section.',
                            'jch-optimize'
                        ),
                        '&lt;/body&gt;',
                        '&lt;head&gt;',
                        '&lt;body&gt;'
                    )
                ],
                'loadAsynchronous' => [
                    __('Defer or load JavaScript asychronously', 'jch-optimize'),
                    __(
                        'The \'asnyc\' attribute is added to the combined JavaScript file, so it will be loaded asynchronously to avoid render blocking and speed up download of the web page. <p> If other files/scripts are excluded while preserving execution order, the \'defer\' attribute is instead used and is added to the last combined file(s) following an excluded file/script. <p>This option only works when the combined JavaScript file is placed at the bottom of the page. '
                    )
                ]
            ],
            /**
             * Exclude Preserving Execution Order
             */
            'excludePeoSection'                  => [
                'excludeJs_peo'           => [
                    __('Exclude JavaScript files', 'jch-optimize'),
                    __('Select or add the files you want to exclude', 'jch-optimize')
                ],
                'excludeJsComponents_peo' => [
                    __('Exclude JavaScript files from these plugins', 'jch-optimize'),
                    __(
                        'The plugin will exclude all JavaScript files from the plugins you have selected.',
                        'jch-optimize'
                    )
                ],
                'excludeScripts_peo'      => [
                    sprintf(__('Exclude individual in-page %s', 'jch-optimize'), '&lt;script&gt;\'s'),
                    sprintf(
                        __('Select the internal %s you want to exclude.', 'jch-optimize'),
                        '&lt;script&gt;\'s'
                    )
                ],
                'excludeAllScripts'       => [
                    __('Exclude all internal &lt;script&gt; declarations', 'jch-optimize'),
                    __(
                        'This is useful if you are generating an excess amount of cache files due to the file name of the combined JavaScript file keeps changing and you can\'t identify which SCRIPT declaration is responsible',
                        'jch-optimize'
                    )
                ]
            ],
            /**
             * Remove JS file
             */
            'removeJsSection'                    => [
                'remove_js' => [
                    __('Remove JavaScript files', 'jch-optimize'),
                    __(
                        'select or add the JavaScript files you want to prevent from loading on the site\'s pages.',
                        'jch-optimize'
                    )
                ]
            ],
            /**
             * Reduce Unused JavaScript
             */
            'reduceUnusedJavascriptSection'      => [
                'pro_reduce_unused_js_enable' => [
                    __('Enable', 'jch-optimize')
                ],
                'pro_criticalJs'              => [
                    __('Critical JavaScript files', 'jch-optimize'),
                    __(
                        'select any files required to perform initial render to exclude from the Reduce Unused JavaScript feature.',
                        'jch-optimize'
                    )
                ],
                'pro_criticalScripts'         => [
                    __('Critical scripts', 'jch-optimize'),
                    __(
                        'Enter any substring of any scripts here that you need to perform any initial render.',
                        'jch-optimize'
                    )
                ],
                'pro_criticalModules'         => [
                    __('Critical modules', 'jch-optimize'),
                    __(
                        'If your critical scripts you have added above is using any modules you may want to enter them here to exclude them from being dynamically loaded into the DOM.'
                    )
                ],
                'pro_criticalModulesScripts'  => [
                    __('Critical inline modules', 'jch-optimize'),
                    __('You can exclude inline modules in a similar manner as outlined above.')
                ],
                'pro_defer_criticalJs'        => [
                    __('Defer critical js'),
                    __(
                        'The critical JavaScript will be deferred or loaded asynchronously by default to avoid render-blocking. However, if your template uses JavaScript to perform any of the initial render above the fold, you may want to disable this to ensure the critical JavaScript loads before the page starts rendering.'
                    )
                ]
            ],
        ];
    }

    /**
     * Settings on Page Cache
     *
     * @return array
     */
    public static function pageCacheTab(): array
    {
        return [
            /**
             * Page Cache
             */
            'pageCacheSection' => [
                'cache_enable'                   => [
                    __('Enable', 'jch-optimize'),
                    __('Enable page caching', 'jch-optimize')
                ],
                'pro_cache_platform'             => [
                    __('Platform specific', 'jch-optimize'),
                    __('Enable if HTML output on mobile differs from desktop.', 'jch-optimize')
                ],
                'page_cache_exclude_form_users'  => [
                    __('Exclude form users', 'jch_optimize'),
                    __(
                        'Disable caching for users who have posted a form until the cache expires. May be useful for some sites that update content on user interaction such as shopping carts.',
                        'jch-optimize'
                    )
                ],
                'page_cache_lifetime'            => [
                    __('Cache lifetime', 'jch-optimize'),
                    __(
                        'The period of time for which the page cache will be valid. Be sure to set this lower that the cache lifetime of combined files at all times.',
                        'jch-optimize'
                    )
                ],
                'cache_exclude'                  => [
                    __('Exclude urls', 'jch-optimize'),
                    __('Enter any part of a url to exclude that page from caching.', 'jch-optimize')
                ],
                'page_cache_ignore_query_values' => [
                    __('Ignore queries', 'jch-optimize'),
                    __(
                        'Add the keys for any query parameter here that you do not want to be used to compute cache IDs. This is useful in cases where different query values do not translate to different content so you want a single cached page to be served to all users regardless of the value of this query parameter, as in the case of a marketing campaing, for e.g.'
                    )
                ],
                'pro_capture_cache_enable'       => [
                    __('Use Http Requests', 'jch-optimize'),
                    __(
                        '<p>Will generate static resources to return via HTTP request so pages are shipped without calling PHP again and significantly reduce server response time. This currently only works on servers that are configured to use .htaccess in the root of the site, such as Apache.<p>Will automatically be disabled if platform specific caching is required.<p>Currently not compatible with multi-sites.'
                    )
                ],

            ]
        ];
    }

    /**
     * Settings on Media Tab
     *
     * @return array
     */
    public static function mediaTab(): array
    {
        return [
            /**
             * Add image Attributes
             */
            'addImageAttributesSection' => [
                'img_attributes_enable' => [
                    __('Enable', 'jch-optimize')
                ]
            ],
            /**
             * Sprite Generator
             */
            'spriteGeneratorSection'    => [
                'csg_enable'         => [
                    __('Enable', 'jch-optimize')
                ],
                'csg_direction'      => [
                    __('Sprite build direction', 'jch-optimize'),
                    __('Determine in which direction the images must be placed in sprite.', 'jch-optimize')
                ],
                'csg_wrap_images'    => [
                    __('Wrap images', 'jch-optimize'),
                    sprintf(
                        __(
                            'Will wrap images in sprite into another row or column if the length of the sprite becomes longer than %s',
                            'jch-optimize'
                        ),
                        '2000px'
                    )
                ],
                'csg_exclude_images' => [
                    __('Exclude image from sprite', 'jch-optimize'),
                    __(
                        'You can exclude one or more of the images if they are displayed incorrectly.',
                        'jch-optimize'
                    )
                ],
                'csg_include_images' => [
                    __('Include additional images in sprite', 'jch-optimize'),
                    __(
                        'You can include additional images in the sprite to the ones that were selected by default. Use this option cautiously, as these files are likely to display incorrectly.',
                        'jch-optimize'
                    )
                ]
            ],
            /**
             * Lazy-Load Images
             */
            'lazyLoadSection'           => [
                'lazyload_enable'            => [
                    __('Enable', 'jch-optimize'),
                ],
                'lazyload_autosize'          => [
                    __('Autosize images', 'jch-optimize'),
                    __(
                        'This setting attempts to maintain aspect ratio of images being lazy-loaded to prevent blank spaces under the images after they\'re loaded or distortions in rendering.'
                    )
                ],
                'pro_lazyload_effects'       => [
                    __('Enable effects', 'jch-optimize'),
                    __('Enable to use fade-in effects when images are scrolled into view.', 'jch-optimize')
                ],
                'pro_lazyload_iframe'        => [
                    __('Lazy load iframes', 'jch-optimize'),
                    sprintf(
                        __('If enabled will also lazyload %s elements.', 'jch-optimize'),
                        '&lt;iframe&gt;'
                    )
                ],
                'pro_lazyload_audiovideo'    => [
                    __('Audio/Video', 'jch-optimize'),
                    sprintf(
                        __('Will lazyload %1$s and %2$s elements that are below the fold.'),
                        '&lt;audio&gt;',
                        '&lt;video&gt;'
                    )
                ],
                'pro_lazyload_bgimages'      => [
                    __('Background images', 'jch-optimize'),
                    __(
                        'Will lazyload background images.',
                        'jch-optimize'
                    )
                ],
                'excludeLazyLoad'            => [
                    __('Exclude these images', 'jch-optimize'),
                    __(
                        'select or manually add the urls of the images you want to exclude from lazy load.',
                        'jch-optimize'
                    )
                ],
                'pro_excludeLazyLoadFolders' => [
                    __('Exclude these folders', 'jch-optimize'),
                    __('Exclude all the images in the selected folders.', 'jch-optimize')
                ],
                'pro_excludeLazyLoadClass'   => [
                    __('Exclude these classes', 'jch-optimize'),
                    sprintf(
                        __(
                            'Exclude all images that has these classes declared on the %s element',
                            'jch-optimize'
                        ),
                        '&lt;img&gt;'
                    )
                ]
            ]
        ];
    }

    /**
     * Settings on Http/2 tab
     *
     * @return array
     */
    public static function preloadsTab(): array
    {
        return [
            /**
             * Http2 Push
             */
            'http2PushSection'              => [
                'http2_push_enable'         => [
                    __('Enable', 'jch-optimize'),
                ],
                'pro_http2_preload_modules' => [
                    __('Preload JavaScript modules', 'jch-optimize'),
                    __(
                        'You may see the alert \'Avoid chaining critical requests\' on PageSpeed and the list includes JavaScript modules. Enable this setting to preload these files and remove them from the critical rendering path.',
                        'jch-optimize'
                    )
                ],
                'pro_http2_file_types'      => [
                    __('File types', 'jch-optimize'),
                    __('Select the file types you want pushed by http/2', 'jch-optimize')
                ],
                'pro_http2_include'         => [
                    __('Include files', 'jch-optimize'),
                    __(
                        'Sometimes some files are dynamically loaded so you can add these files here. Be sure any file added here are loaded on all pages and that you include the full file path including any queries etc. Only the following file extensions are supported: .js, .css, .webp, .gif, .png, .jpg, .woff, .woff2',
                        'jch-optimize'
                    )
                ],
                'pro_http2_exclude'         => [
                    __('Exclude files', 'jch-optimize'),
                    __(
                        'If you see any warnings in the browser console that the preloaded files weren\'t used within a few seconds you can exclude these files here',
                        'jch-optimize'
                    )
                ]
            ],
            /**
             * Largest Contentful Paint Images
             */
            'largestContentfulPaintSection' => [
                'pro_lcp_images_enable' => [
                    __('Enable', 'jch-optimize')
                ],
                'pro_lcp_images'        => [
                    __('LCP images', 'jch-optimize'),
                    __(
                        'Add your LCP images here to have them preloaded with a high priority on whichever page they appear.'
                    )
                ]
            ],
            /**
             * Optimize Fonts
             */
            'optimizeFontsSection'          => [
                'pro_optimizeFonts_enable' => [
                    __('Enable', 'jch-optimize')
                ],
                'pro_force_swap_policy'    => [
                    __('Force swap policy', 'jch-optimize'),
                    __(
                        'If font-display is already set in your @font-face content, the policy will be set to \'swap\'. This ensures your text are visible while the sites load and improve your PageSpeed scores.',
                        'jch-optimize'
                    )
                ],
                'pro_optimize_font_files'  => [
                    __('Optimize these files', 'jch-optimize'),
                    __(
                        'If you have CSS files only containing @font-face content, or font files you want preloaded, you can add them here so they\'ll be optimized like the Google Font files. You may need to manually enter any external domain below to have them preconnected.',
                        'jch-optimize'
                    )
                ],

            ],
            'preconnectThirdPartySection'   => [
                'pro_preconnect_domains_enable' => [
                    __('Enable', 'jch-optimize'),
                ],
                'pro_preconnect_domains'        => [
                    __('Preconnect domains', 'jch-optimize'),
                    __(
                        'Add external domains you want preconnected here. Be sure to include the scheme also, eg., \'https://example.com\'.',
                        'jch-optimize'
                    )
                ]
            ]
        ];
    }

    /**
     * Settings on CDN tab
     *
     * @return array
     */
    public static function cdnTab(): array
    {
        return [
            /**
             * CDN
             */
            'cdnSection' => [
                'cookielessdomain_enable' => [
                    __('Enable', 'jch-optimize'),
                ],
                'cdn_scheme'              => [
                    __('CDN scheme', 'jch-optimize'),
                    __(
                        'Select the scheme that you want prepended to the CDN/Cookieless domain',
                        'jch-optimize'
                    )
                ],
                'cookielessdomain'        => [
                    __('Domain 1', 'jch-optimize'),
                    __('Enter value for Domain #1 here', 'jch-optimize')
                ],
                'staticfiles'             => [
                    __('Static files 1', 'jch-optimize'),
                    __(
                        'Select the static file types that you want to be loaded over Domain #1',
                        'jch-optimize'
                    )
                ],
                'pro_customcdnextensions' => [
                    __('Custom extensions 1', 'jch-optimize'),
                    __(
                        'To add custom extensions of file types to be loaded over CDN on Domain 1, type the extension in the textbox and press the \'Add item\' button',
                        'jch-optimize'
                    )
                ],
                'pro_cookielessdomain_2'  => [
                    __('Domain 2', 'jch-optimize'),
                    __('Enter value for Domain #2 here', 'jch-optimize')
                ],
                'pro_staticfiles_2'       => [
                    __('Static files 2', 'jch-optimize'),
                    __(
                        'Select the static file types that you want to be loaded over Domain #2',
                        'jch-optimize'
                    )
                ],
                'pro_cookielessdomain_3'  => [
                    __('Domain 3', 'jch-optimize'),
                    __('Enter value for Domain #3 here', 'jch-optimize')
                ],
                'pro_staticfiles_3'       => [
                    __('Static files 3', 'jch-optimize'),
                    __(
                        'Select the static file types that you want to be loaded over Domain #3',
                        'jch-optimize'
                    )
                ]
            ]
        ];
    }

    /**
     * Settings on Optimize Image tab
     *
     * @return array
     */
    public static function optimizeImage(): array
    {
        return [
            /**
             * Global Section
             */
            'globalSection'    => [
                'ignore_optimized'           => [
                    __('Ignore optimized images', 'jch-optimize'),
                    __(
                        'Will not attempt to optimize any images in subfolders that have already been marked as optimized.',
                        'jch-optimize'
                    )
                ],
                'pro_next_gen_images'        => [
                    __('Next-Gen images', 'jch-optimize'),
                    __(
                        'When enabled the plugin will convert the images that are optimized to webp format and load these instead.',
                        'jch-optimize'
                    )
                ],
                /*	'pro_web_old_browsers' => [
                                __( 'Support old browsers', 'jch-optimize' ),
                                sprintf( __( 'Plugin will wrap WEBP image in a %s element along with original image so browsers without WEBP support can fall back to the original image.', 'jch-optimize' ), '&lt;picture&gt;' )
                        ], */
                'pro_load_webp_images'       => [
                    __('Load WEBP images', 'jch-optimize'),
                    __('Loads available WEBP images in place of the original ones on your web pages.', 'jch-optimize')
                ],
                'pro_gen_responsive_images'  => [
                    __('Generate responsive images', 'jch-optimize'),
                    __(
                        'While optimizing images, will also create different sized images to be used at different CSS breakpoints'
                    )
                ],
                'pro_load_responsive_images' => [
                    __('Load responsive images', 'jch-optimize'),
                    __(
                        'Use responsive images where available by adding srcset attributes on image elements and creating CSS breakpoints for background images.'
                    )
                ],
                'lossy'                      => [
                    __('Lossy optimization', 'jch-optimize'),
                    __(
                        'Levels are either Lossy or Lossless, the default is Lossy. With Lossy optimization images will be more optimized and smaller but may result in a small reduction of quality, most times invisible to the untrained eye. If you don\'t want that then you can choose Lossless instead. The images will not be as optimized but there will be no loss of quality.',
                        'jch-optimize'
                    )
                ],
                'save_metadata'              => [
                    __('Save metadata', 'jch-optimize'),
                    __(
                        'The Optimize Image API will remove all metadata from images while optimizing including any copyrights or Exif textual content not part of the actual image to produce the smallest possible file size. If you wish to retain this information at a small loss in optimization then you should enable this option.',
                        'jch-optimize'
                    )
                ]
            ],
            /**
             * Automatically Optimize Section
             */
            'autoApiSection'   => [
                'pro_api_resize_mode' => [
                    __('Auto resize images', 'jch-optimize'),
                    __(
                        'Images will be resized automatically to the dimensions as shown on screen. If you use an application that shows the images larger when you hover over them be sure to use different images for the large ones. For this to work the url to your site must be available publicly.',
                        'jch-optimize'
                    )
                ]
            ],
            /**
             * Manually optimize Section
             */
            'manualApiSection' => [
                'recursive' => [
                    __('Recurse in subfolders', 'jch-optimize'),
                    __(
                        'Will optimize all images in selected folders and recurse into subfolders infinitely. If disabled, only the images in the selected folders will be optimized. Subfolders will be ignored.',
                        'jch-optimize'
                    )
                ]
            ]
        ];
    }

    /**
     * Settings on Miscellaneous tab
     *
     * @return array
     */
    public static function miscellaneous(): array
    {
        return [
            /**
             * Reduce Dom Section
             */
            'reduceDomSection'    => [
                'pro_reduce_dom'    => [
                    __('Enable', 'jch-optimize')
                ],
                'pro_html_sections' => [
                    __('HTML sections', 'jch-optimize'),
                    __('Select which HTML sections you would like to load asynchronously.')
                ]
            ],
            /**
             * Mode Switcher
             */
            'modeSwitcherSection' => [
                'pro_disableModeSwitcher' => [
                    __('Disable Mode Switcher', 'jch-optimize'),
                    __('If you don\'t want the Mode Switcher menu to be displayed you can disable it here.')
                ]
            ]
        ];
    }
}
