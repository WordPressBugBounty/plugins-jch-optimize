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

/** @var Icons $icons */
$aToggleIcons = $icons->compileToggleFeaturesIcons($icons->getToggleSettings());
$aAdvancedToggleIcons = $icons->compileToggleFeaturesIcons($icons->getAdvancedToggleSettings());

?>

<div id="control-panel-block" class="grid" style="grid-template-rows: auto;">
    <div class="g-col-12 g-col-lg-8" style="grid-row-end: span 2;">
        <div id="combine-files-block" class="admin-panel-block">
            <h4><?= __('Combine Files Automatic Settings', 'jch-optimize') ?></h4>
            <p class="alert alert-secondary"><?= __(
                    'Choose one of the six presets to automatically configure the settings concerned with the optimization of CSS and JavaScript files. You can also disable the combine files feature here and exclude files on the Configurations tab.',
                    'jch-optimize'
                ) ?></p>
            <div class="icons-container">
                <?= $icons->printIconsHTML(
                    $icons->compileToggleFeaturesIcons($icons->getCombineFilesEnableSetting())
                ) ?>
                <div class="icons-container">
                    <?= $icons->printIconsHTML($icons->compileAutoSettingsIcons($icons->getAutoSettingsArray())) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="g-col-12 g-col-lg-4" style="grid-row-end: span 3">
        <div id="utility-settings-block" class="admin-panel-block">
            <h4><?= __('Utility Tasks', 'jch-optimize') ?></h4>
            <p class="alert alert-secondary"><?= __(
                    'Some useful utility tasks. Hover over each title for more description.',
                    'jch-optimize'
                ) ?></p>
            <div>
                <div class="icons-container">
                    <?= $icons->printIconsHTML(
                        $icons->compileUtilityIcons(
                            $icons->getUtilityArray(
                                ['browsercaching', 'orderplugins', 'keycache', 'recache', 'bulksettings']
                            )
                        )
                    ) ?>
                    <div class="icons-container">
                        <?= $icons->printIconsHTML(
                            $icons->compileUtilityIcons($icons->getUtilityArray(['cleancache']))
                        ) ?>
                        <div>
                            <br>
                            <div>
                                <em><span><?= __('No of files: ', 'jch-optimize') ?></span>
                                    <span class="numFiles-container"><img
                                                src="<?= JCH_PLUGIN_URL . 'media/core/images/loader.gif' ?>"/></span></em>
                            </div>
                            <div>
                                <em><span><?= __('Size of files: ', 'jch-optimize') ?></span>
                                    <span class="fileSize-container"><img
                                                src="<?= JCH_PLUGIN_URL . 'media/core/images/loader.gif' ?>"/></span></em>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="g-col-12 g-col-lg-8" style="grid-row-end: span 3;">
        <div id="toggle-settings-block" class="admin-panel-block">
            <h4><?= __('Basic Features', 'jch-optimize') ?></h4>
            <p class="alert alert-secondary"><?= __(
                    'Click each button to toggle these features on/off individually. Enable the ones you need for your site. Some may need additional configuration. You can access these settings from the Configurations tab.',
                    'jch-optimize'
                ) ?></p>
            <div>
                <div class="icons-container">
                    <?= $icons->printIconsHTML($aToggleIcons) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="g-col-12 g-col-lg-4" style="grid-row-end: span 2;">
        <div id="advanced-settings-block" class="admin-panel-block">
            <h4><?= __('Advanced Settings', 'jch-optimize') ?></h4>
            <p class="alert alert-secondary"><?= __(
                    'Click each button to toggle these features on/off individually. Enable the ones you need for your site. Some may need additional configuration. You can access these settings from the Configurations tab.',
                    'jch-optimize'
                ) ?></p>
            <div>
                <div class="icons-container">
                    <?= $icons->printIconsHTML($aAdvancedToggleIcons) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="g-col-12">
        <div id="copyright-block" class="admin-panel-block">
            <strong>JCH Optimize Pro <?= JCH_VERSION ?></strong> Copyright 2022 &copy; <a
                    href="https://www.jch-optimize.net/">JCH Optimize</a>
            <?php if( ! JCH_PRO ): ?>
            <p class="alert alert-success"><a
                        href="https://www.jch-optimize.net/subscribes/subscribe-wordpress/new/wpstarter.html?layout=default&coupon=JCHGOPRO20">Upgrade
                    to the PRO version today</a> with 20% off using JCHGOPRO20</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- for testing
<script>
    window.onload = function(){
        var el = document.querySelector('#intermediate span.hasPopover');
        var popover = new bootstrap.Popover(el, {html: true});
        popover.show();
    }
</script>

-->
<div id="bulk-settings-modal-container" class="modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('Bulk Settings Operations', 'jch-optimize') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="close"></button>
            </div>
            <div class="modal-body p-4">
                <?= $this->fetch('main_bulk_settings.php', $data) ?>
            </div>
        </div>
    </div>
</div>