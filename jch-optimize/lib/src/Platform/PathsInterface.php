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

namespace JchOptimize\Core\Platform;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

/**
 * Interface PathsInterface
 * @package JchOptimize\Core\Interfaces
 *
 * A $path variable is an absolute path on the local filesystem to a file or folder without any trailing slashes.
 * A $relPath has no leading slash.
 * A $directory ($dir) is the absolute path to a directory with a trailing slash
 * A $relDir has no leading slash
 * A $folder is an alias of a $directory
 */
interface PathsInterface
{
    /**
     * Returns url to the media folder (Can be root relative based on platform)
     *
     * @return string
     */
    public function mediaUrl(): string;

    /**
     * Returns root relative path to the /assets/ folder
     *
     * @param bool $pathonly
     *
     * @return string
     */
    public function relAssetPath(bool $pathonly = false): string;

    /**
     * Path to the directory where generated sprite images are saved
     *
     * @param bool $isRootRelative If true, return the root relative path with trailing slash;
     * if false, return the absolute path without trailing slash.
     *
     * @return string
     */
    public function spritePath(bool $isRootRelative = false): string;

    /**
     * Find the absolute path to a resource given a root relative path
     *
     * @param string $url Root relative path of resource on the site
     *
     * @return string
     */
    public function absolutePath(string $url): string;

    /**
     * The base folder for rewrites when the combined files are delivered with PHP using mod_rewrite.
     * Generally the parent directory for the /media/ folder with a root relative path
     *
     * @return string
     */
    public function rewriteBaseFolder(): string;

    /**
     * Convert the absolute filepath of a resource to a url
     *
     * @param string $path Absolute path of resource
     *
     * @return string
     */
    public function path2Url(string $path): string;

    /**
     * @return string Absolute path to root of site
     */
    public function rootPath(): string;

    public function basePath(): string;

    /**
     * Parent directory of the folder where the original images are backed up in the Optimize Image Feature
     *
     * @return string
     */
    public function backupImagesParentDir(): string;

    /**
     * Returns path to the directory where combined css/js files are saved.
     *
     * @param bool $isRootRelative If true, returns root relative path, otherwise, the absolute path
     *
     * @return string
     */
    public function cachePath(bool $isRootRelative = true): string;

    /**
     * Path to the directory where next generation images are stored in the Optimize Image Feature
     *
     * @param bool $isRootRelative
     *
     * @return string
     */
    public function nextGenImagesPath(bool $isRootRelative = false): string;

    /**
     * Path to the directory where icons for Icon Buttons are found
     *
     * @return string
     */
    public function iconsUrl(): string;

    /**
     * Path to the logs file
     *
     * @return string
     */
    public function getLogsPath(): string;

    /**
     * Returns base path of the home page excluding host
     *
     * @return string
     */
    public function homeBasePath(): string;

    /**
     * Returns base path of home page including host
     *
     * @return string
     */
    public function homeBaseFullPath(): string;

    /**
     * Url used in administrator settings page to perform certain tasks
     *
     * @param string $name
     *
     * @return string
     */
    public function adminController(string $name): string;

    /**
     * The directory where CaptureCache will store HTML files
     *
     * @return string
     */
    public function captureCacheDir(bool $isRootRelative = false): string;

    /**
     * The directory for storing cache
     *
     * @return string
     */
    public function cacheDir(): string;

    /**
     * The directory where blade templates are kept
     *
     * @return string
     */
    public function templatePath(): string;

    /**
     * The directory where compiled versions of blade templates are stored
     *
     * @return string
     */
    public function templateCachePath(): string;

    /**
     * @param bool $isRootRelative
     * @return string
     */
    public function responsiveImagePath(bool $isRootRelative = false): string;

    public function liveSite(): string;
}
