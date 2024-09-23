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

namespace JchOptimize\Platform;

defined('_WP_EXEC') or die('Restricted access');

use JchOptimize\Core\Interfaces\Paths as PathsInterface;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\Utils;

use function add_query_arg;
use function admin_url;
use function dirname;
use function get_current_blog_id;
use function get_option;
use function home_url;
use function is_multisite;
use function rtrim;
use function set_url_scheme;
use function str_replace;
use function trailingslashit;
use function wp_upload_dir;

use const DIRECTORY_SEPARATOR;
use const JCH_CACHE_DIR;

abstract class Paths implements PathsInterface
{
    public static function mediaUrl(): string
    {
        return JCH_PLUGIN_URL . 'media';
    }

    /**
     * Find the absolute path to a resource given a root relative path
     *
     * @param   string  $url  Root relative path of resource on the site
     *
     * @return string
     */
    public static function absolutePath(string $url): string
    {
        $home_path = trailingslashit(self::rootPath());
        $rootPath = str_replace('/\\', DIRECTORY_SEPARATOR, $home_path);

        //We can now concatenate root path to url path to get absolute path on filesystem
        return rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . ltrim($url, '\\/');
    }

    /**
     * @return string Absolute path to root of site
     */
    public static function rootPath(): string
    {
        $home = set_url_scheme((string)get_option('home'), 'http');
        $site_url = set_url_scheme((string)get_option('siteurl'), 'http');

        if (!empty($home) && 0 < strcasecmp($home, $site_url)) {
            $wp_path_rel_to_home = str_ireplace($home, '', $site_url); /* $site_url - $home */
            $pos = strripos(
                str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']),
                trailingslashit($wp_path_rel_to_home)
            );
            $home_path = substr($_SERVER['SCRIPT_FILENAME'], 0, $pos);
        } elseif (!empty($home) && 0 > strcasecmp($home, $site_url)) {
            $wp_path_rel_to_home = str_ireplace($home, '', $site_url); /* $site_url - $home */
            $pos = strripos(str_replace('\\', '/', ABSPATH), trailingslashit($wp_path_rel_to_home));
            $home_path = substr(ABSPATH, 0, $pos);
        } else {
            $home_path = ABSPATH;
        }

        return untrailingslashit($home_path);
    }

    public static function basePath(): string
    {
        return self::rootPath();
    }

    /**
     * Returns root relative path to the /assets/ folder
     *
     * @param   bool  $pathonly
     *
     * @return string
     */
    public static function relAssetPath(bool $pathonly = false): string
    {
        if ($pathonly) {
            return SystemUri::basePath() . 'jch-optimize/media/assets';
        }

        return plugins_url() . '/jch-optimize/media/assets';
    }

    /**
     * The base folder for rewrites when the combined files are delivered with PHP using mod_rewrite. Generally the parent directory for the
     * /media/ folder with a root relative path
     *
     * @return string
     */
    public static function rewriteBaseFolder(): string
    {
        static $rewrite_base;

        if (!isset($rewrite_base)) {
            $uri = Utils::uriFor(plugins_url());
            $rewrite_base = trailingslashit($uri->getPath());
        }

        return $rewrite_base;
    }

    /**
     * Path to the directory where generated sprite images are saved
     *
     * @param   bool  $isRootRelative  If true, return the root relative path; if false, return the absolute path.
     *
     * @return string
     */
    public static function spritePath(bool $isRootRelative = false): string
    {
        if ($isRootRelative) {
            return JCH_PLUGIN_URL . 'media/sprites';
        }

        return JCH_PLUGIN_DIR . 'media/sprites';
    }

    /**
     * Convert the absolute filepath of a resource to a url
     *
     * @param   string  $path  Absolute path of resource
     *
     * @return string
     */
    public static function path2Url(string $path): string
    {
        $oUri = Utils::uriFor(SystemUri::toString());
        $sBaseFolder = SystemUri::basePath();

        $abs_path = str_replace(DIRECTORY_SEPARATOR, '/', self::rootPath());
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);

        $sUriPath = (string)$oUri->withPath(
            $sBaseFolder .
            (str_replace($abs_path . DIRECTORY_SEPARATOR, '', $path))
        );

        return $sUriPath;
    }

    /**
     * Parent directory of the folder where the original images are backed up in the Optimize Image Feature
     *
     * @return string
     */
    public static function backupImagesParentDir(): string
    {
        return WP_CONTENT_DIR . DIRECTORY_SEPARATOR;
    }

    public static function nextGenImagesPath(bool $isRootRelative = false): string
    {
        $wp_upload_dir = wp_upload_dir(null, true, true);
        $sRelJchUploadPath = '/jch-optimize/ng';

        if ($isRootRelative) {
            $uri = Utils::uriFor($wp_upload_dir['baseurl'] . $sRelJchUploadPath);

            return (string)$uri->withScheme('')->withHost('')->withUserInfo('');
        }

        return $wp_upload_dir['basedir'] . $sRelJchUploadPath;
    }

    public static function iconsUrl(): string
    {
        return JCH_PLUGIN_URL . 'media/core/icons';
    }

    public static function getLogsPath(): string
    {
        return JCH_PLUGIN_DIR . 'logs';
    }

    public static function homeBasePath(): string
    {
        return home_url('', 'relative');
    }

    public static function homeBaseFullPath(): string
    {
        return home_url('/');
    }

    /**
     * Url used in administrator settings page to perform certain tasks
     *
     * @param   string  $name
     *
     * @return string
     */
    public static function adminController(string $name): string
    {
        $url = add_query_arg(['task' => $name], admin_url('admin-ajax.php?action=onclickicon'));

        return wp_nonce_url($url, $name);
    }

    /**
     * Url to access Ajax functionality
     *
     * @param   string  $function  Action to be performed by Ajax function
     *
     * @return string
     */
    public static function ajaxUrl(string $function): string
    {
        return add_query_arg(['action' => $function], admin_url('admin-ajax.php'));
    }

    public static function captureCacheDir(bool $isRootRelative = false): string
    {
        $captureCacheDir = self::cachePath($isRootRelative) . '/html';

        if ($isRootRelative) {
            $captureCacheURI = Utils::uriFor($captureCacheDir);

            return $captureCacheURI->getPath();
        }

        return $captureCacheDir;
    }

    /**
     * Returns path to the directory where static combined css/js files are saved.
     *
     * @param   bool  $isRootRelative  If true, returns root relative path, otherwise, the absolute path
     *
     * @return string
     */
    public static function cachePath(bool $isRootRelative = true): string
    {
        $id = '';

        if (is_multisite()) {
            $id = get_current_blog_id();
        }

        if ($isRootRelative) {
            return JCH_CACHE_URL . 'assets' . $id;
        } else {
            return JCH_CACHE_DIR . 'assets' . $id;
        }
    }

    public static function cacheDir(): string
    {
        return JCH_CACHE_DIR;
    }

    public static function templateCachePath(): string
    {
        return JCH_CACHE_DIR . 'compiled_templates';
    }

    public static function templatePath(): string
    {
        return dirname(__FILE__, 3) . '/tmpl';
    }

    public static function responsiveImagePath(bool $isRootRelative = false): string
    {
        $wp_upload_dir = wp_upload_dir(null, true, true);
        $sRelJchUploadPath = '/jch-optimize/rs';

        if ($isRootRelative) {
            $uri = Utils::uriFor($wp_upload_dir['baseurl'] . $sRelJchUploadPath);

            return (string)$uri->withScheme('')->withHost('')->withUserInfo('');
        }

        return $wp_upload_dir['basedir'] . $sRelJchUploadPath;
    }
}
