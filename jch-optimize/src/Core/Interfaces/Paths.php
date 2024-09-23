<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Interfaces;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

/**
 * Interface PathsInterface
 * @package JchOptimize\Core\Interfaces
 *
 * A $path variable is considered an absolute path on the local filesystem without any trailing slashes.
 * Relative $paths will be indicated in their names or parameters.
 * A $folder is a representation of a directory with front and trailing slashes.
 * A $directory is the filesystem path to a directory with a trailing slash.
 */
interface Paths
{
    /**
     * Returns url to the media folder (Can be root relative based on platform)
     *
     * @return string
     */
    public static function mediaUrl(): string;

    /**
     * Returns root relative path to the /assets/ folder
     *
     * @param   bool  $pathonly
     *
     * @return string
     */
    public static function relAssetPath(bool $pathonly = false): string;

    /**
     * Path to the directory where generated sprite images are saved
     *
     * @param   bool  $isRootRelative  If true, return the root relative path with trailing slash; if false, return the absolute path without trailing slash.
     *
     * @return string
     */
    public static function spritePath(bool $isRootRelative = false): string;

    /**
     * Find the absolute path to a resource given a root relative path
     *
     * @param   string  $url  Root relative path of resource on the site
     *
     * @return string
     */
    public static function absolutePath(string $url): string;

    /**
     * The base folder for rewrites when the combined files are delivered with PHP using mod_rewrite. Generally the parent directory for the
     * /media/ folder with a root relative path
     *
     * @return string
     */
    public static function rewriteBaseFolder(): string;

    /**
     * Convert the absolute filepath of a resource to a url
     *
     * @param   string  $path  Absolute path of resource
     *
     * @return string
     */
    public static function path2Url(string $path): string;

    /**
     * @return string Absolute path to root of site
     */
    public static function rootPath(): string;

    /**
     * Parent directory of the folder where the original images are backed up in the Optimize Image Feature
     *
     * @return string
     */
    public static function backupImagesParentDir(): string;

    /**
     * Returns path to the directory where static combined css/js files are saved.
     *
     * @param   bool  $isRootRelative  If true, returns root relative path, otherwise, the absolute path
     *
     * @return string
     */
    public static function cachePath(bool $isRootRelative = true): string;

    /**
     * Path to the directory where next generation images are stored in the Optimize Image Feature
     *
     * @param   bool  $isRootRelative
     *
     * @return string
     */
    public static function nextGenImagesPath(bool $isRootRelative = false): string;

    /**
     * Path to the directory where icons for Icon Buttons are found
     *
     * @return string
     */
    public static function iconsUrl(): string;

    /**
     * Path to the logs file
     *
     * @return string
     */
    public static function getLogsPath(): string;

    /**
     * Returns base path of the home page excluding host
     *
     * @return string
     */
    public static function homeBasePath(): string;

    /**
     * Returns base path of home page including host
     *
     * @return string
     */
    public static function homeBaseFullPath(): string;

    /**
     * Url used in administrator settings page to perform certain tasks
     *
     * @param   string  $name
     *
     * @return string
     */
    public static function adminController(string $name): string;

    /**
     * The directory where CaptureCache will store HTML files
     *
     * @return string
     */
    public static function captureCacheDir(): string;

    /**
     * The directory for storing cache
     *
     * @return string
     */
    public static function cacheDir(): string;

    /**
     * The directory where blade templates are kept
     *
     * @return string
     */
    public static function templatePath(): string;

    /**
     * The directory where compiled versions of blade templates are stored
     *
     * @return string
     */
    public static function templateCachePath(): string;
}
