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

defined('_JCH_EXEC') or die('Restricted Access');

use JchOptimize\Core\Admin\Icons;

$url = admin_url('admin-ajax.php');
$page = wp_nonce_url(add_query_arg(
    [
        'action' => 'optimizeimages',
        'mode' => 'byUrls'
    ],
    $url
), 'jch_optimize_image');

$aAutoOptimize = [
    [
        'link'    => '',
        'icon'    => 'auto_optimize.png',
        'name'    => __('Optimize Images', 'jch-optimize'),
        'script'  => 'onclick="jchOptimizeImageApi.optimizeImages(\'' . $page . '\', \'auto\'); return false;"',
        'id'      => 'auto-optimize-images',
        'class'   => '',
        'proonly' => true
    ]
];

$page = wp_nonce_url(add_query_arg(
    [
        'action' => 'optimizeimages',
        'mode' => 'byFolders'
    ],
    $url
), 'jch_optimize_image');

$aManualOptimize = [
    [
        'link'    => '',
        'icon'    => 'manual_optimize.png',
        'name'    => __('Optimize Images', 'jch-optimize'),
        'script'  => 'onclick="jchOptimizeImageApi.optimizeImages(\'' . $page . '\', \'manual\'); return false;"',
        'id'      => 'manual-optimize-images',
        'class'   => '',
        'proonly' => true
    ]
];

/** @var Icons $icons */
?>

<div class="grid">
    <div class="g-col-12 g-col-lg-6">
        <div id="api2-utilities-block" class="admin-panel-block">
            <h4><?= __('Optimize Image Utility Settings', 'jch-optimize') ?></h4>
            <p class="alert alert-secondary"><?= __('Hover over each title for additional description') ?></p>
            <div class="icons-container">
                <?= $icons->printIconsHTML($icons->compileUtilityIcons($icons->getApi2utilityArray())) ?>
            </div>
        </div>
    </div>
    <div class="g-col-12 g-col-lg-6">
        <div id="auto-optimize-block" class="admin-panel-block">
            <h4><?= __('Optimize Images By URLs', 'jch-optimize') ?></h4>
            <p class="alert alert-secondary"><?= __(
                'JCH Optimize will scan the pages of your site for images to optimize.',
                'jch-optimize'
            ) ?></p>
            <div class="icons-container">
                <?= $icons->printIconsHTML($aAutoOptimize) ?>
            </div>
        </div>
    </div>
    <div class="g-col-12">
        <script>
            jQuery(document).ready(function () {
                jQuery('#file-tree-container').fileTree(
                    {
                        root: '',
                        script: ajaxurl + '?action=filetree&_wpnonce=' + jch_filetree_url_nonce,
                        expandSpeed: 1000,
                        collapseSpeed: 1000,
                        multiFolder: false
                    }, function (file) {
                    })
            })
        </script>
        <div id="manual-optimize-block" class="admin-panel-block">
            <div id="optimize-images-container">
                <h4><?= __('Optimize Images By Folders', 'jch-optimize') ?></h4>
                <p class="alert alert-secondary"><?= __(
                    'Use the file tree to select the subfolders and files you want to optimize. Files will be optimized in subfolders recursively by default, you can disable this. If you want to rescale your images while optimizing, enter the new width and height in the respective columns beside each image on the right hand side.',
                    'jch-optimize'
                ) ?></p>
                <div class="grid">
                    <div class="g-col-12 g-col-lg-3 g-col-xl-4">
                        <div id="file-tree-container"></div>
                    </div>
                    <div class="g-col-12 g-col-lg-6 g-col-xl-6">
                        <div id="files-container"></div>
                    </div>
                    <div class="g-col-12 g-col-lg-3 g-col-xl-2">
                        <div class="icons-container">
                            <div class=""><?= $icons->printIconsHTML($aManualOptimize) ?></div>
                        </div>
                    </div>
                </div>
                <div style="clear:both"></div>
            </div>
        </div>
    </div>
</div>
<div id="optimize-images-modal-container" class="modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Optimizing Images</h5>
            </div>
            <div class="modal-body">

            </div>
        </div>
    </div>
</div>
