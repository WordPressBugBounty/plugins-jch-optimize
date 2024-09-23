=== JCH Optimize ===

Contributors: codealfa
Tags: performance, pagespeed, cache, optimize, seo
Tested up to: 6.5.3
Stable tag: 4.2.1
License: GPLv3 or later
Requires at least: 5.0
Requires PHP: 8.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This plugin automatically performs several front end optimizations to your site to boost performance and increase PageSpeed scores.

== Description ==
JCH Optimize improves the performance of your website by performing several optimizations to the HTML page aimed at improving the Web Vitals measured by PageSpeed Insights, such as First Contentful Paint (FCP), Largest Contentful Paint (LCP), Speed Index (SI), Cumulative Layout Shift (CLS), Time to Interactive (TTI), and Total Blocking Time (TBT). These metrics attempt to quantify the quality of the user experience. JCH Optimize can improve these metrics to provide a better experience for your users and improve your PageSpeed scores.

= Optimizations Performed By JCH Optimize =

JCH Optimize optimizes your pages by automatically performing some recommended strategies offered by PageSpeed, which include:

- **Minify CSS and JavaScript files and the HTML.** Unnecessary whitespaces and other characters are removed to reduce network payload sizes and script parse times.
- **Eliminate Render-Blocking Resources.** Inline critical JavaScript and CSS and defer all non-critical resources to prevent resources from blocking the first paint of your page.
- **Defer offscreen images.** Offscreen and hidden images are lazy-loaded after all critical resources have finished loading to lower Time To Interactive (TTI).
- **Add Width and Height attributes to images.** Set an explicit width and height on image elements to reduce layout shifts and improve CLS.
- **Enable text compression.** Boilerplate codes are placed in the .htaccess files at the site's directory root to instruct the server to serve text-based resources with compression (deflate or brotli) to minimize total network bytes.
- **Preload critical resources.** Automatically analyzes each page to identify critical resources to load with a high priority by HTTP/2 enabled servers to improve LCP time.
- **Reduce initial server response.** A page cache feature is included in the plugin that integrates well with the other optimization features and significantly reduces time-to-first-byte.

= Benefits of Using JCH Optimize =
Research has confirmed that 40% of visitors will leave a website if it takes more than 4 seconds to download. Also, Google and other search engines have indicated that their ranking algorithm increasingly factors website download speed. The benefits of using JCH Optimize then include:

- **Improved user experience.** Your users will have a pleasant experience as they browse your site.
- **Improved SEO.** Your rankings in Google search pages can increase and improve organic visibility in internet searches.
- **Improved conversions.** Your website revenue also increases with increased traffic volume and visitor retention.

= Pro Version available =
There is a pro version available with more optimization features and options and premium support with assistance to configure plugin to resolve conflicts and improve performance on our [website](https://www.jch-optimize.net/subscribes/subscribe-wordpress/levels.html).

= How to use =

To use, first temporarily deactivate all page caching features and plugins, then use the 'Automatic Settings' (Minimum - Optimum) to configure the plugin. The 'Automatic Settings' are concerned with the combining of the CSS and javascript files, and the management of the combined files, and automatically sets the options in the 'Automatic Settings Groups'. Use the Exclude options to exclude files or plugins that don't work so well when combined with JCH Optimize. You can then try the other optimization features in turn such as Sprite Generator, Add Image Attributes, Lazy Load Images, CDN/Cookieless Domain, Optimize CSS Delivery, etc., based on the optimization needs of your site. Flush all your cache before re-enabling caching features and plugins.

= Documentation =

Visit our [documentation](https://www.jch-optimize.net/documentation.html) on the main plugin site for more information on how the plugin works and how to configure it to improve your scores on [GtMetrix](https://gtmetrix.com/) and [PageSpeed Insights](https://developers.google.com/speed/pagespeed/insights/)

= Advanced Features and Premium Support =

If you need assistance on your website in configuring the plugin to resolve any conflicts or if you need access to more advanced features such as Http/2 support, Remove unused CSS, Lazy-load iframes, Optimize Images, using multiple domains with CDN, then there's a [Pro version](https://www.jch-optimize.net/subscribe/levels.html#wordpress) available on a subscription basis. With an active subscription you get premium technical support through our ticket system, access to downloads of new versions, and access to our Optimize Image API.


== Installation ==

Just install from your WordPress "Plugins|Add New" screen. Manual installation is as follows:

1. Upload the zip-file and unzip it in the /wp-content/plugins/ directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to `Settings -> JCH Optimize` and enable the options you want
4. Use the Automatic Settings (Minimum - Optimum) to configure the plugin. This automatically sets the options in the 'Automatic Settings Groups'. You can then try the other manual options to further configure the plugin and optimize your site. Use the Exclude options to exclude files/plugins/images that don't work so well with the plugin.


== Frequently Asked Questions ==

= How does the plugin speed up your site? =

The plugin modifies the generated HTML of your website in ways that makes it faster to download and parsed by the browser. Simply put, the changes makes the webpage and resources smaller, and reduces the number of http requests the browser has to make to render the page. The result is a better user experience and higher search engine rankings.

= How do I know if it's working? =

After installing and activating the plugin, combining CSS and javascript files are selected by default so it should start working right away. If you look at your web page and it doesn't look any different that's a good sign...maybe. To confirm if it's working, take a look at the HTML page source. You can do that in most browsers by right clicking on the page and selecting that option. You should see the links to your CSS/Js files removed and replaced by the aggregated file URL in the source that looks like this:
`/wp-content/plugins/jch-optimize/assets/wp-content/plugins/gz/63fccd8dc82e3f5da947573d8ded3bd4.css`

= There's no CSS Formatting after enabling the plugin =

The combined files are accessed by the browser via a jscss.php file in the `/wp-content/plugins/jch-optimize/assets/` directory. If you're not seeing any formatting on your page it means that the browser is not accessing this file for some reason. View the source of your page and try to access the JCH generated url to the combined file in your browser. You should see an error message that can guide you in fixing the problem. Generally it's a file permission issue so ensure the file at '/wp-content/plugins/jch-optimize/assets/jscss.php` has the same permission setting as your /index.php file (usually 644) and make sure all the folders in this hierarchy have the same permissions as your htdocs or public_html folder(Usually 644).

= How do I reverse the change JCH Optimize makes to my website? =

Simply deactivate or uninstall the plugin to reverse any changes it has made. The plugin doesn't modify any existing file or code but merely manipulates the HTML before it is sent to the browser. Any apparent persistent change after the plugin is deactivated is due to caching so ensure to flush all your WordPress, third party or browser cache.

== Changelog ==

= 4.2.1 =
* Fixed security vulnerability on path traversal. Unprivileged users could access directory information on the Optimize Image page
* Bug Fix Sort Items on Page Cache tab not working
* Use the loading attribute to lazy load images instead of JavaScript. JavaScript only used for background images and audio/video
* Asynchronously loaded CSS in Optimize CSS Delivery feature placed at bottom of page.

= 4.2.0 =
* Add responsive image feature to further boost LCP
* Add support for resizing WEBP images to create responsive images
* Bug Fix: Backup images were only saved when WEBP images were generated
* Bug Fix: Fix fatal error on servers without mbstring support

= 4.1.1 =
* Removed support for deprecated Wincache storage.
* Improved caching to reduce server resource usage.
* Bug Fix: Couldn't exclude JavaScript with async attributes.

= 4.1.0 =
* Minimum PHP version is now 8.0
* Add feature to preload LCP images with high-fetch priority
* Add setting to easily find the Elements above fold value. Only works if Debug plugin is enabled.
* Improved Optimize image script
* Replaced CDN setting preconnect CDNs and HTTP.2 setting push CDN files with Preconnect section for third-party origins

= 4.0.1 =
* Bug Fix: Recache didn't work on some servers.
* Bug Fix: Full compatibility with Windows broken.
* Fix security vulnerability with broken access control to settings
* Fix security vulnerability with the Optimize Image metafile
* Add setting to ignore query parameter on page cache

= 4.0.0 =
* Minimum PHP version updated to PHP 7.4
* Added Recache feature. [Pro version only]
* Added icon on Dashboard to load WEBP images. [Pro version only]
* Added Bul Settings Operations feature to export, reset, and import settings.
* Modified the JavaScript exclude settings to make them more user-friendly.
* Rearranged some settings on the Configurations tab to make them more intuitive.
* Other minor bug fixes and improvements.

= 3.2.3 =
* Bug Fix: Cross Site Scripting security vulnerability on the Configuration tab.
* Bug Fix: Conflict with XML sitemaps when using the Optimize Fonts feature.
* Other minor bug fixes and improvements.

= 3.2.2 =
* Bug Fix: PHP Error when Clean Cache is accessed from the admin menu on some sites
* Bug Fix: PHP Error with function utf8_strlen on some sites.
* Other minor bug fixes and improvements.

= 3.2.1 =
* Added setting to optionally disable deletion of expired cache.
* Added settings for excluding critical modules in Reduce Unused JavaScript. [Pro version only]
* Bug fix: Some external domains were not being preconnected when Optimize Fonts is enabled. [Pro version only]
* Bug fix: Imported Google fonts were not being handled properly. [Pro version only]
* Bug fix: Reduce Unused JavaScript wasn't working properly on some sites. [Pro version only]

= 3.2.0 =
* Improve method to generate cache key for Optimize CSS Delivery feature to prevent excess generating of cache.
* Bug fix: CDN feature broke processing same images used multiple times in scrset.
* Improve cache management to reduce cache build up on some sites.
* Bug fix: Fix issue with .htaccess codes causing 500 errors on older versions of Apache.
* The Http/2 tab on the Configurations tab was changed to Preload and the Optimize Fonts feature moved to this tab.
* Bug fix: Fixed bug with lazy loading audio/video set o autoplay. [Pro version only]
* The Optimize Google Fonts feature was expanded to optimize all fonts. [Pro version only]
* Modules are now being loaded dynamically in the Reduce Unused Javascript feature. [Pro version only]
* Added setting to preload modules in the Http/2 Preload feature. [Pro version only]
* Added setting o lazy-load background images defined int eh CSS files to the Lazy-load feature. [Pro version only]
* Added setting to change existing font-display policy to swap in the HTTP/2 preload feature. [Pro version only]
* Bug fix: Images in <picture> elements were not converted to WEBP. [Pro version only]

= 3.1.0 =
* Minimum required PHP version is now 7.3
* Added support for Brotli compression in .htaccess optimization.
* Added new tab in admin for Page Cache so individual files can be viewed and deleted.
* Added support for different cache storage types (APCu, Memcached, Redis, WinCache) [Pro version]
* New page cache feature to serve static content via HTTP Request without calling PHP to significantly improve server load time. [Pro Version]
* Added 'Remove Unused Javascript' feature [Pro Version]

= 3.0.1 =
* Bug Fix: Fix PHP error when using Page Cache on some sites

= 3.0.0 =
* Complete redo of Admin Configuration page to be more user-friendly and aesthetic.
* Added Remove javascript/Remove CSS setting.
* Added Utility feature to generate new cache hash.
* Added Smart Combine feature. [Pro version only]
* Added Reduce DOM feature. [Pro version only]
* Using icon images instead of font icons on action buttons on dashboard.

= 2.10.0 =
* New Feature: Added admin menu item to delete cache.
* Improvement: When Lazyload image is enabled, hidden images are also only loaded when they become visible.
* Improvement: Added support for older browsers that don't use WEBP images.
* Improvement: Recursing into subfolders while using the Manual Optimize Image section is now optional.
* Improvement: Urls in srcsets are now being processed when using Automatic Optimize Image option and are also now being converted to WEBP images where necessary.
* Other minor bug fixes and improvements.

= 2.9.0 =
* New Feature: Support for webp images in teh Optimize Image Feature. [Pro version]
* New Feature: Utility options to restore optimized images and to delete folder with backup images.
* Improvements: Improved implementation of http/2.
* Improvements: Improved implementation of Optimize CSS Delivery to determine HTML above the fold particularly with large number of menu items
* Other minor bug fixes and improvements.

= 2.8.0 =
* New Feature: Basic settings for Http2 feature now available in Free version.
* New Feature: Optimize Google Fonts feature to speed up the loading of Google Fonts. [Pro version]
* New Feature: Ability to add files to the Http2 Push if critical files are being loaded dynamically so cannot normally be accessed by the plugin. [Pro Version]
* New Feature: Added preconnect resource hints settings for CDN domains.
* Improvements: Improved method to generate key hash for Optimize CSS Delivery to avoid incorrect critical CSS being loaded on page, so improving Cumulative layout shift across pages.
* Other minor bug fixes and improvements.

= 2.7.0 =
* Improvements: Dynamic CSS Selectors will add CSS to critical CSS even if Remove Unused CSS is disabled.
* Improvements: In the Http/2 push feature, the plugin will now only push woff files or woff2 files instead if present. This avoids pushing font files that are not being used.
* New Feature: Added settings to Lazy-load background images and Audio and Video elements. [Pro Version]
* New Feature: Added settings to add files loaded over CDN to the Link header, and to exclude files in the Http/2 Push feature. [Pro Version]
* New Feature: Added platform specific caching. [Pro Version]
* Other minor bug fixes and improvements.

= 2.6.2 =
* Bug fix. Error in combined CSS files caused by media type in file being different to type in media attribute on the LINK tag.
* Bug Fix: Lazyloading images with srcset attributes broke W3C HTML validation.
* Big Fix: PHP Notice in profiler.php
* Improvements: Organized settings in fieldsets on Settings page
* Improvements: Add Image Attributes will ignore images with both height and width attributes present. If one attribute is present the other will be added based on aspect ratio.
* Improvements: Page cache lifetime setting and handling separated from combined files.

= 2.6.1 =
* Bug fix: Combined files delivery using PHP files were broken

= 2.6.0 =
* New feature: Option to remove unused CSS. This is added as an additional setting in the Optimize CSS Delivery feature. [PRO VERSION]
* New feature: Setting to disable plugin for logged in users in Miscellaneous Settings on Combine CSS/JS tab.
* Improvement: Will now generate different hash for multiple combined files. This will help to reduce build-up of cache.
* Improvement: All excluded and combined javascript files are placed at bottom of page with Premium/Optimum setting.
* Improvement: Add Image Attributes feature now ignores img elements with existing width and height attributes. If one attribute is found the other will be added using aspect ratio of image.

= 2.5.2 =
* Bug fix: Add image attributes will use the same type of delimiter for width/height as that used around the url to avoid potential issues
* Bug Fix: Validate HTML before processing to avoid problems.

= 2.5.1 =
* Bug fix: PHP error in html.php file
* Bug fix: Occasionally shows blank page while using Page Cache

= 2.5.0 =
* Changes to the settings admin page and availability of features
* Bux fixes and code improvements

= 2.4.2 =
* Minor bug fixes and improvements
* Added option to autosize images in Lazyload [PRO VERSION]
* Load CSS file asynchronously using preload attribute instead of javascript in Optimize CSS Delivery [PRO VERSION]
* Fixed bug in Optimize Image not working on Safari [PRO VERSION]

= 2.4.1 =
* Improved compatibility with page caching and third party plugins
* Fixed bug in HTML Minifier library
* Fixed issue with font not showing correctly on some sites
* Fixed bug in Lazy-load feature [PRO VERSION]

= 2.4.0 =
* Minor bug fixes and improvements
* Improved efficiency of caching policy of static assets
* Added Http/2 push feature [PRO VERSION]
* Added support for srcsets and iframe to Lazyload images feature [PRO VERSION] 
* Removed font-face from combined CSS file when Optimize CSS Delivery is enabled [PRO VERSION]

= 2.3.2 =
* Fixed issue with plugin not running on some sites with last version
* Added ability to mark and skip images already optimized in subfolders [PRO VERSION]
* Fixed issue with auto-update of PRO version reverting to FREE version [PRO VERSION]

= 2.3.1 =
* Fixed issue in page cache causing PHP errors

= 2.3.0 =
* Added page cache feature
* Improved support for LiteSpeed Cache
* Other minor bug fixes and improvements.

= 2.2.3 =
* Minor bug fixes and improvement

= 2.2.2 =
* Improved caching to reduce instances of excess cache.
* Fixed issue with xml sitemaps when 'Debug plugin' is enabled.
* Fixed issue with deprecating PHP error using the 'each' function.
* Added minifier for json
* Other bug fixes and improvements.

= 2.2.1 =
* Fixed bug with exclude settings not being saved

= 2.2.0 =
* Expired cache flushed daily
* Codes added to .htaccess file to gzip compress files
* Major improvement to Optimize Image feature handling more images much more efficiently (PRO VERSION)
* Various bug fixes and improvement

= 2.1.0 =
* Ability to exclude files while maintaining original execution order for all Automatic Settings added.
* Ability to select static files for combined css and js files added.
* Cache lifetime hardcoded to 1 day and setting removed.
* 'Exclude javascript dynamically' setting removed.
* Ability to select file type for each CDN domain added.(PRO VERSION)
* CDN feature will use base element to determine the base url for relative urls.(PRO VERSION)
* Automatically exclude images above the fold from Lazy-load feature to avoid css render-blocking issues.(PRO VERSION)
* Improvements in the Optimize CSS Delivery feature.(PRO VERSION)
* Various bug fixes and improvements.

= 2.0.8 =
* Fixed bug creating errors in JchOptimizeSettings
* Removed some exclusion settings
* Fix javascript error in options page
* Other minor bug fixes

= 2.0.7 =
* Fixed conflicts with select plugins that cause JCH Optimize to generate a Fatal Error
* Removed cache lifetime setting. Lifetime hardcoded to 1 day
* Other minor bug fixes and improvement

= 2.0.6 =
* Fix issue with the plugin not running on some sites
* Now Compatible with Google AMP pages
* Added setting to exclude pages from the plugin that don't work well or you don't want optimized
 
= 2.0.5 =
* Couple bug fixes from the last version

= 2.0.4 =
* Improved compatibility with PHP7
* Improved support for Google font files
* Fixed issue with script that flushes expired cache daily
* Other minor fixes and improvements.

= 2.0.3 =
* Fixed bug that was causing some javascript errors in some browsers on some sites.

= 2.0.2 =
* Fixed bug with handling Google font files
* Grouped settings related to the combine CSS/javascript feature together to make it more intuitive to configure and added setting to disable/enable this feature
* Added feature to add missing height and width attributes to img elements
* Fixed bug with lazy-load feature that was affecting other javascript libraries
* Other minor bug fixes and improvements

= 2.0.1 = 
* Fixed issue with CSS Optimize library that caused some pages to load slowly

= 2.0.0 =
* The settings in the backend are rearranged in a more logical and intuitive manner
* Support for up to 3 CDN/Cookieless domains and the ability to select the file type to load over CDN
* Exclude images from Lazy Load based on the folder (useful if you want to exclude all images from an extension), or by the CSS class defined on the image
* Improved compatibility with slideshows and ajax content with the LazyLoad function and also support for non-javascript users (probably some mobile)
* Ability to remove files from loading on the page for eg., if you have more than one jQuery libraries or libraries you're not using like Mootools.
* Psuedo-cron script that flush expired cache daily to reduce the build up of excess cache
* Support for those pesky Google font files that are always blocking on PageSpeed
* Option to 'Leverage Browser Cache' for common resource files.
* Option to correct permissions of files/folders in plugin.
* Added functionality to recursively optimize images in subfolders
* Can scale images during optimization if image dimensions are larger than required.
* Optimized/resized images will be automatically backed up in a folder.
* Developed our own API for optimizing images so we'll no longer be using Kraken.io
* Added language translations for Spanish, French, Russian, German, and Hebrew
* Other improvements to existing features and various bug fixes.

= 1.2.2 =
* Fixed issue in validating HTML that prevented the plugin running on some sites.

= 1.2.1 =
* Fix links to combined file to include scheme and domain for better compatibility with other plugins
* Improved code that manipulates urls in the plugins

= 1.2.0 =
* Fixed bug in Autoloader function that conflicts with other plugins that have classes beginning with 'JCH'
* Fixed bug with HTML Minify removing spaces from inside pre elements when it contains other HTML elements
* Fixed compatibility issue with plugins using PHP internal buffering eg. CDN Linker, cache plugins, etc.
* Will delete plugin options on uninstall
* Multisite supported
* Fixed issue with Optimize Images not working with open_basedir setting (PRO VERSION)
* Now able to automatically update the Pro version when your download id is saved in the plugin (PRO VERSION)

= 1.1.4 =
* Improved method of accessing HTML for optimization considering levels of buffering
* Corrected function used to access home url in backend so that exclude options lists can be populated
* Fixed bug in and improved HTML minification library
* Fixed bug with Sprite Generator
* Fixed bug with CDN/Cookie-less domain feature (PRO VERSION)
* Improved Image Optimization feature (PRO VERSION)

= 1.1.3 =
* Fixed issue with the setting 'Use url rewrite - Yes (Without Options+SynLinks)' not working properly
* Fixed issue with combine javascript options sometimes creates javascript errors
* Now using Kraken.io API to optimize images (PRO VERSION)

= 1.1.2 =
* Fixed compatibility issue with XML sitemaps and feeds.
* Minor bug fixes

= 1.1.1 =
* Improved code running in admin section
* Add Profiler menu item on Admin Bar to review the times taken for the plugin methods to run.
* Keep HTML comments in 'Basic' HTML Minification level. Required for some plugins to work eg. Nextgen gallery.
* Saving cache in non-PHP files to make it compatible with  WP Engine platform.
* Minor bug fixes and improvements.

= 1.1.0 =
* Added visual indicators to show which Automatic setting is enabled
* Added multiselect exclude options so it's easier to find files/plugins to exclude from combining if they cause problems
* Bug fixes and improvements in the HTML, CSS, and javascript minification libraries
* Added levels of HTML minification

= 1.0.2 =
* Fixed bug in HMTL Minify library manifested on XHTML templates
* Fails gracefully on PHP5.2

= 1.0.1 =
* First public release on WordPress plugins repository.
