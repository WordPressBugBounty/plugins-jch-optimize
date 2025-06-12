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
        'link'    => '',
        'icon'    => 'fa fa-crop',
        'name'    => __('Optimize Images', 'jch-optimize'),
        'script'  => 'onclick="jchOptimizeImageApi.optimizeImages(\'' . $page . '\', \'auto\'); return false;"',
        'id'      => 'auto-optimize-images',
        'class'   => [],
        'proonly' => true
];

$page = wp_nonce_url(add_query_arg(
    [
        'action' => 'optimizeimages',
        'mode' => 'byFolders'
    ],
    $url
), 'jch_optimize_image');

$aManualOptimize = [
        'link'    => '',
        'icon'    => 'fa fa-crop-alt',
        'name'    => __('Optimize Images', 'jch-optimize'),
        'script'  => 'onclick="jchOptimizeImageApi.optimizeImages(\'' . $page . '\', \'manual\'); return false;"',
        'id'      => 'manual-optimize-images',
        'class'   => [],
        'proonly' => true
];

$iconsRenderer = clone $this;
$iconsRenderer->setTemplatePath(JCH_PLUGIN_DIR . 'layouts/dashicons');

/** @var Icons $icons */
?>

<div class="jch-bs-container-fluid box-sizing-border-box">
    <div class="row g-3 box-sizing-border-box">
        <div class="col-12 col-md-8 box-sizing-border-box">
            <div class="bg-white p-4" style="min-height: 470px">
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
                <div id="optimize-images-container">
                        <div class="jch-bs-container-fluid box-sizing-border-box">
                            <div class="row box-sizing-border-box">
                                <div class="col-12 col-md-4 box-sizing-border-box">
                                    <div id="file-tree-container">
                                        <img class="ms-3" src="<?= JCH_PLUGIN_URL . '/media/core/images/loader.gif' ?>">
                                    </div>
                                </div>
                                <div class="col-12 col-md-8 box-sizing-border-box">
                                    <div id="files-container">
                                        <img class="ms-3" src="<?= JCH_PLUGIN_URL . '/media/core/images/loader.gif' ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div style="clear:both"></div>
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
        </div>
        <div class="col box-sizing-border-box">
            <div class="jch-bs-card mb-3">
                <div class="jch-bs-card-header">
                    <h2>
                        <span class="fa fa-folder-open"></span>
                        <?php _e('Optimize By Folders', 'jch-optimize') ?>
                    </h2>
                </div>
                <div class="jch-bs-card-body">
                    <nav class="jch-dash-icons px-3 pb-3">
                        <ul class="nav flex-wrap">
                            <?= $iconsRenderer->fetch('icon.php', ['displayData' => $aManualOptimize]) ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
        <div class="col box-sizing-border-box">
            <div class="jch-bs-card mb-3">
                <div class="jch-bs-card-header">
                    <h2>
                        <span class="fa fa-external-link-square-alt"></span>
                        <?php _e('Optimize By URLs', 'jch-optimize') ?>
                    </h2>
                </div>
                <div class="jch-bs-card-body">
                    <nav class="jch-dash-icons px-3 pb-3">
                        <ul class="nav flex-wrap">
                            <?= $iconsRenderer->fetch('icon.php', ['displayData' => $aAutoOptimize]) ?>
                        </ul>
                    </nav>
                </div>
            </div>
            <div class="jch-bs-card mb-3">
                <div class="jch-bs-card-header">
                    <h2>
                        <span class="fa fa-tools"></span>
                        <?php _e('Utility Settings', 'jch-optimize') ?>
                    </h2>
                </div>
                <div class="jch-bs-card-body">
                    <nav class="jch-dash-icons px-3 pb-3">
                        <ul class="nav flex-wrap">
                            <?php $buttons = $icons->compileUtilityIcons($icons->getApi2UtilityArray()) ?>
                            <?php foreach ($buttons as $button): ?>
                            <?= $iconsRenderer->fetch('icon.php', ['displayData' => $button]) ?>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
