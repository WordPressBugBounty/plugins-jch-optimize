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

namespace JchOptimize\Core\Admin\Ajax;

use _JchOptimizeVendor\V91\GuzzleHttp\Psr7\UriResolver;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use JchOptimize\Core\Admin\AdminHelper;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Uri\Utils;

use function array_diff;
use function defined;
use function in_array;
use function is_dir;
use function preg_match;
use function scandir;

defined('_JCH_EXEC') or die('Restricted access');

class FileTree extends Ajax
{
    /**
     * @return string
     */
    public function run(): string
    {
        // Website document root (fixed base)
        $rootDir = Helper::appendTrailingSlash($this->paths->rootPath());
        $rootDir = $this->adminHelper->normalizePath($rootDir);
        $rootReal = rtrim($this->adminHelper->normalizePath((string)realpath($rootDir)), '/');

        // The expanded directory in the folder tree (user input)
        // Decode first, then sanitize. This catches encoded traversal like %2e%2e
        $dirRaw = $this->input->getString('dir', '');
        $dirRaw = rawurldecode($dirRaw);
        $dirRaw = Helper::removeLeadingSlash($dirRaw);
        if ($dirRaw !== '') {
            $dirRaw = Helper::appendTrailingSlash($dirRaw);
        }

        // Strict allow-list for relative path tokens. Adjust if you truly need more.
        // Disallow control chars, backslashes, wildcard chars, and fragments.
        if ($dirRaw !== '' && !preg_match('#^[A-Za-z0-9/_\.\-]*$#', $dirRaw)) {
            return ''; // or JSON error in your framework; path rejected
        }
        if (str_contains($dirRaw, "\0") || str_contains($dirRaw, '\\') || str_contains($dirRaw, '://')) {
            return '';
        }

        // Reject any '..' segment early (defense in depth; realpath clamp still applied below)
        if (preg_match('#(?:^|/)\\.\\.(?:/|$)#', $dirRaw)) {
            return '';
        }

        // Which side of the Explorer view are we rendering?
        $ExplorerView = $this->input->getWord('jchview', '');
        // Will be set to 1 if this is the root directory
        $isRootDir = $this->input->getBool('initial', false);

        // === Securely resolve the directory to explore ===
        $explorerDir = $this->secureJoin($rootReal, $dirRaw);
        if ($explorerDir === false || !is_dir($explorerDir) || !is_readable($explorerDir)) {
            return ''; // or JSON error; outside base / not readable
        }

        $files = @scandir($explorerDir);
        if ($files === false) {
            return '';
        }
        $files = array_diff($files, array('..', '.'));

        $directories = [];
        $imageFiles = [];

        foreach ($files as $file) {
            $filePathAbs = $this->adminHelper->normalizePath($explorerDir . '/' . $file);
            $fileReal = realpath($filePathAbs);

            // Canonicalize and re-clamp children as well (protects against symlinked entries)
            if ($fileReal === false) {
                continue;
            }
            $fileReal = $this->adminHelper->normalizePath($fileReal);

            // Ensure the child still lives under root
            if ($fileReal !== $rootReal && !str_starts_with($fileReal, $rootReal . '/')) {
                continue;
            }

            // Compute RELATIVE path from root for UI/URIs
            $relFilePath = ltrim(substr($fileReal, strlen($rootReal)), '/');

            if (
                is_dir($fileReal)
                && !in_array($file, ['jch_optimize_backup_images', '.jch', 'jch-optimize'], true)
            ) {
                $directories[] = Utils::uriFor($relFilePath);
            } elseif (
                $ExplorerView !== 'tree'
                && preg_match('#' . AdminHelper::$optimizeImagesFileExtRegex . '#i', $file)
                && @is_file($fileReal)
            ) {
                $imageFiles[] = Utils::uriFor($relFilePath);
            }
        }

        $rootUri = Utils::uriFor($rootReal . '/');

        $items = function (string $explorerView, array $directories, array $imageFiles, UriInterface $rootUri): string {
            $item = '<ul class="jqueryFileTree">';

            foreach ($directories as $directory) {
                $item .= '<li class="directory collapsed">';

                if ($explorerView != 'tree') {
                    $item .= "<input type=\"checkbox\" value=\"{$directory->getPath()}\">";
                }

                $item .= "<a href=\"#\" data-url=\"{$directory->getPath()}\">"
                    . rawurldecode(Utils::filename($directory))
                    . '</a>';
                $item .= '</li>';
            }

            if ($explorerView != 'tree') {
                foreach ($imageFiles as $image) {
                    $imagePath = $this->adminHelper->normalizePath(UriResolver::resolve($rootUri, $image));

                    $style = $this->adminHelper->isAlreadyOptimized($imagePath) ? ' class="already-optimized"' : '';
                    $file_name = rawurldecode(Utils::filename($image));
                    $ext = Utils::fileExtension($image);

                    $item .= <<<HTML
<li class="file ext_{$ext}">
    <input type="checkbox" value="{$image->getPath()}">
    <span{$style}><a href="#" data-url="{$image->getPath()}">{$file_name}.{$ext}</a> </span>
    <span><input type="text" size="10" maxlength="5" name="width"></span>
    <span><input type="text" size="10" maxlength="5" name="height"></span>
</li>
HTML;
                }
            }

            $item .= '</ul>';

            return $item;
        };

        //generate the response
        $response = '';

        if ($ExplorerView != 'tree') {
            $width = $this->utility->translate('Width');
            $height = $this->utility->translate('Height');
            $response .= <<<HTML
<div id="files-container-header">
    <ul class="jqueryFileTree">
        <li class="check-all">
            <input type="checkbox"><span><em>Check all</em></span>
            <span><em>{$width}</em></span>
            <span><em>{$height}</em></span>
        </li>
    </ul>
</div>
HTML;
        }

        if ($isRootDir && $ExplorerView == 'tree') {
            $response .= <<<HTML
<div class="files-content">
    <ul class="jqueryFileTree">
        <li class="directory expanded root"><a href="#" data-root="{$rootReal}/" data-url="">&lt;root&gt;</a>
            {$items($ExplorerView, $directories, $imageFiles, $rootUri)}
        </li>
    </ul>
</div>
HTML;
        } elseif ($ExplorerView != 'tree') {
            $response .= <<<HTML
<div class="files-content">
{$items($ExplorerView, $directories, $imageFiles, $rootUri)}
</div>
HTML;
        } else {
            $response .= $items($ExplorerView, $directories, $imageFiles, $rootUri);
        }

        return $response;
    }

    /**
     * Securely join a user-supplied relative path to a fixed base directory.
     * Returns canonical absolute path INSIDE $base, or false on any violation.
     */
    private function secureJoin(string $baseReal, string $userRel): string|false
    {
        $baseReal = rtrim($this->adminHelper->normalizePath((string)realpath($baseReal)), '/');
        if ($baseReal === '') {
            return false;
        }

        // Absolute paths or Windows drives not allowed for user input
        if ($userRel === '') {
            $userRel = '.'; // stay at base
        }
        if ($userRel[0] === '/' || preg_match('#^[A-Za-z]:/#', $userRel)) {
            return false;
        }

        // Build candidate and canonicalize
        $candidate = $this->adminHelper->normalizePath(Helper::appendTrailingSlash($baseReal) . ltrim($userRel, '/'));
        $real = realpath($candidate);
        if ($real === false) {
            return false;
        }
        $real = rtrim($this->adminHelper->normalizePath($real), '/');

        // Clamp: ensure $real is within $baseReal (symlinks included)
        if ($real === $baseReal || str_starts_with($real, Helper::appendTrailingSlash($baseReal))) {
            return $real;
        }

        return false;
    }
}
