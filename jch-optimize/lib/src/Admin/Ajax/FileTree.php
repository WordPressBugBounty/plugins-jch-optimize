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

namespace JchOptimize\Core\Admin\Ajax;

use JchOptimize\Core\Admin\Helper as AdminHelper;
use JchOptimize\Platform\Paths;
use JchOptimize\Platform\Utility;

\defined('_JCH_EXEC') or exit('Restricted access');
class FileTree extends \JchOptimize\Core\Admin\Ajax\Ajax
{
    public function run(): string
    {
        // Website document root
        $root = Paths::rootPath();
        // The expanded directory in the folder tree
        $dir = \urldecode($this->input->getString('dir', '')).'/';
        // Which side of the Explorer view are we rendering? Folder tree or subdirectories and files
        $view = \urldecode($this->input->getWord('jchview', ''));
        // Will be set to 1 if this is the root directory
        $initial = \urldecode($this->input->getString('initial', '0'));
        $files = \array_diff(\scandir($root.$dir), ['..', '.']);
        $directories = [];
        $imageFiles = [];
        $i = 0;
        $j = 0;
        foreach ($files as $file) {
            if (\is_dir($root.$dir.$file) && !\in_array($file, ['jch_optimize_backup_images', '.jch', 'jch-optimize'])) {
                $directories[$i]['name'] = $file;
                $directories[$i]['file_path'] = $dir.$file;
                ++$i;
            } elseif ('tree' != $view && \preg_match('#'.\JchOptimize\Core\Admin\Ajax\OptimizeImage::$fileExtRegex.'#i', $file) && @\file_exists($root.$dir.$file)) {
                $imageFiles[$j]['ext'] = \preg_replace('/^.*\\./', '', $file);
                $imageFiles[$j]['name'] = $file;
                $imageFiles[$j]['file_path'] = $dir.$file;
                $imageFiles[$j]['optimized'] = AdminHelper::isAlreadyOptimized($root.$dir.$file);
                ++$j;
            }
        }
        $items = function (string $view, array $directories, array $imageFiles): string {
            $item = '<ul class="jqueryFileTree">';
            foreach ($directories as $directory) {
                $item .= '<li class="directory collapsed">';
                if ('tree' != $view) {
                    $item .= '<input type="checkbox" value="'.$directory['file_path'].'">';
                }
                $item .= '<a href="#" data-url="'.$directory['file_path'].'">'.\htmlentities($directory['name']).'</a>';
                $item .= '</li>';
            }
            if ('tree' != $view) {
                foreach ($imageFiles as $image) {
                    $style = $image['optimized'] ? ' class="already-optimized"' : '';
                    $file_name = \htmlentities($image['name']);
                    $item .= <<<HTML
<li class="file ext_{$image['ext']}">
\t<input type="checkbox" value="{$image['file_path']}">
\t<span{$style}><a href="#" data-url="{$image['file_path']}">{$file_name}</a> </span>\t
\t<span><input type="text" size="10" maxlength="5" name="width"></span>
\t<span><input type="text" size="10" maxlength="5" name="height"></span>
</li>\t\t
HTML;
                }
            }
            $item .= '</ul>';

            return $item;
        };
        // generate the response
        $response = '';
        if ('tree' != $view) {
            $width = Utility::translate('Width');
            $height = Utility::translate('Height');
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
        if ($initial && 'tree' == $view) {
            $response .= <<<HTML
    <div class="files-content">
        <ul class="jqueryFileTree">
            <li class="directory expanded root"><a href="#" data-root="{$root}" data-url="">&lt;root&gt;</a>

                {$items($view, $directories, $imageFiles)}

            </li>
        </ul>
    </div>
HTML;
        } elseif ('tree' != $view) {
            $response .= <<<HTML
\t<div class="files-content">
\t
\t{$items($view, $directories, $imageFiles)}
\t
\t</div>
HTML;
        } else {
            $response .= $items($view, $directories, $imageFiles);
        }

        return $response;
    }

    /**
     * @param string $dir
     * @param string $view
     * @param string $path
     */
    private function item(string $file, $dir, $view, $path): string
    {
        $file_path = $dir.$file;
        $root = Paths::rootPath();
        $anchor = '<a href="#" data-url="'.$file_path.'">'.\htmlentities($file).'</a>';
        $html = '';
        if ('tree' == $view) {
            $html .= $anchor;
        } else {
            $html .= '<input type="checkbox" value="'.$file_path.'">';
            if ('dir' == $path) {
                $html .= $anchor;
            } else {
                $html .= '<span';
                if (AdminHelper::isAlreadyOptimized($root.$dir.$file)) {
                    $html .= ' class="already-optimized"';
                }
                $html .= '>'.\htmlentities($file).'</span><span><input type="text" size="10" maxlength="5" name="width" ></span><span><input type="text" size="10" maxlength="5" name="height" ></span>';
            }
        }

        return $html;
    }
}
