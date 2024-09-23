<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads.
 *
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Interfaces;

\defined('_JCH_EXEC') or exit('Restricted access');

/**
 * Interface PathsInterface.
 */
interface Paths
{
    /**
     * Returns url to the media folder (Can be root relative based on platform).
     */
    public static function mediaUrl(): string;

    /**
     * Returns root relative path to the /assets/ folder.
     */
    public static function relAssetPath(bool $pathonly = \false): string;

    /**
     * Path to the directory where generated sprite images are saved.
     *
     * @param bool $isRootRelative if true, return the root relative path with trailing slash;
     *                             if false, return the absolute path without trailing slash
     */
    public static function spritePath(bool $isRootRelative = \false): string;

    /**
     * Find the absolute path to a resource given a root relative path.
     *
     * @param string $url Root relative path of resource on the site
     */
    public static function absolutePath(string $url): string;

    /**
     * The base folder for rewrites when the combined files are delivered with PHP using mod_rewrite.
     * Generally the parent directory for the /media/ folder with a root relative path.
     */
    public static function rewriteBaseFolder(): string;

    /**
     * Convert the absolute filepath of a resource to a url.
     *
     * @param string $path Absolute path of resource
     */
    public static function path2Url(string $path): string;

    /**
     * @return string Absolute path to root of site
     */
    public static function rootPath(): string;

    public static function basePath(): string;

    /**
     * Parent directory of the folder where the original images are backed up in the Optimize Image Feature.
     */
    public static function backupImagesParentDir(): string;

    /**
     * Returns path to the directory where static combined css/js files are saved.
     *
     * @param bool $isRootRelative If true, returns root relative path, otherwise, the absolute path
     */
    public static function cachePath(bool $isRootRelative = \true): string;

    /**
     * Path to the directory where next generation images are stored in the Optimize Image Feature.
     */
    public static function nextGenImagesPath(bool $isRootRelative = \false): string;

    /**
     * Path to the directory where icons for Icon Buttons are found.
     */
    public static function iconsUrl(): string;

    /**
     * Path to the logs file.
     */
    public static function getLogsPath(): string;

    /**
     * Returns base path of the home page excluding host.
     */
    public static function homeBasePath(): string;

    /**
     * Returns base path of home page including host.
     */
    public static function homeBaseFullPath(): string;

    /**
     * Url used in administrator settings page to perform certain tasks.
     */
    public static function adminController(string $name): string;

    /**
     * The directory where CaptureCache will store HTML files.
     */
    public static function captureCacheDir(): string;

    /**
     * The directory for storing cache.
     */
    public static function cacheDir(): string;

    /**
     * The directory where blade templates are kept.
     */
    public static function templatePath(): string;

    /**
     * The directory where compiled versions of blade templates are stored.
     */
    public static function templateCachePath(): string;

    public static function responsiveImagePath(bool $isRootRelative = \false): string;
}
