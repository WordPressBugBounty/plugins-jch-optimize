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
        //Website document root
        $rootDir = Helper::appendTrailingSlash($this->paths->rootPath());
        //The expanded directory in the folder tree
        $currentExplorerTreeRelDir = Helper::removeLeadingSlash(
            Helper::appendTrailingSlash(
                $this->input->getString('dir', '')
            )
        );
        //Which side of the Explorer view are we rendering? Folder tree or subdirectories and files
        $ExplorerView = $this->input->getWord('jchview', '');
        //Will be set to 1 if this is the root directory
        $isRootDir = $this->input->getBool('initial', false);

        $explorerDir = $rootDir . $currentExplorerTreeRelDir;
        $files = array_diff(scandir($this->adminHelper->normalizePath($explorerDir)), array('..', '.'));

        $directories = [];
        $imageFiles = [];

        foreach ($files as $file) {
            $filePath = $this->adminHelper->normalizePath($explorerDir . $file);
            $relFilePath = $currentExplorerTreeRelDir . $file;

            if (
                is_dir($filePath)
                && !in_array($file, [
                    'jch_optimize_backup_images',
                    '.jch',
                    'jch-optimize'
                ])
            ) {
                $directories[] = Utils::uriFor($relFilePath);
            } elseif (
                $ExplorerView != 'tree'
                && preg_match('#' . AdminHelper::$optimizeImagesFileExtRegex . '#i', $file)
                && @file_exists($filePath)
            ) {
                $imageFiles[] = Utils::uriFor($relFilePath);
            }
        }

        $rootUri = Utils::uriFor($rootDir);

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
            <li class="directory expanded root"><a href="#" data-root="{$rootDir}" data-url="">&lt;root&gt;</a>

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
}
