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

use _JchOptimizeVendor\V91\Slim\Views\PhpRenderer;
use JchOptimize\Core\Admin\Icons;

/** @var Icons $icons */
$aToggleIcons = $icons->compileToggleFeaturesIcons($icons->getToggleSettings());
$aAdvancedToggleIcons = $icons->compileToggleFeaturesIcons($icons->getAdvancedToggleSettings());
/**
 *
 */
/** @var PhpRenderer $iconRenderer */
$iconRenderer = clone $this;
$iconRenderer->setTemplatePath(JCH_PLUGIN_DIR . 'layouts/dashicons');

?>

<div class="jch-bs-container-fluid grid box-sizing-border-box">
    <div class="g-col-2 g-col-lg-1 box-sizing-border-box">
        <div class="jch-bs-card">
            <div class="jch-bs-card-header">
                <h2><span class="fa fa-file-download me-2"></span><?= __('Optimize Files Automatic Settings', 'jch-optimize') ?></h2>
            </div>
            <div class="jch-bs-card-body">
                <?php $button = $icons->compileToggleFeaturesIcons($icons->getCombineFilesEnableSetting())[0]; ?>
                    <div class="jch-dash-icons-switcher ms-3 mb-3 px-1">
                        <div id="<?= $button['id'] ?>" <?= $button['script']; ?>>
                            <div class="form-check form-switch d-flex align-items-center">
                                <input class="form-check-input me-2" type="checkbox" role="switch"
                                    <?= $button['enabled'] ? 'checked' : ''; ?> id="flexSwitchCheckDefault">
                                <label class="form-check-label fs-6" for="flexSwitchCheckDefault">
                                    <?= $button['enabled'] ? 'Enabled' : 'Disabled'; ?>
                                </label>

                            </div>
                        </div>
                    </div>
                <nav class="jch-dash-icons px-3 pb-3">
                    <ul class="nav flex-wrap">
                        <?php $buttons = $icons->compileAutoSettingsIcons($icons->getAutoSettingsArray()) ?>
                        <?php foreach ($buttons as $button): ?>
                        <?= $iconRenderer->fetch('icon.php', ['displayData' => $button]) ?>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
    <div class="g-col-2 g-col-lg-1 box-sizing-border-box">
        <div class="jch-bs-card">
            <div class="jch-bs-card-header">
                <h2><span class="fa fa-exclamation-circle me-2"></span><?= __('Notifications', 'jch-optimize') ?></h2>
            </div>
            <div class="jch-bs-card-body">
                <nav class="jch-dash-icons px-3 pb-3">
                    <ul class="nav flex-wrap">
                        <?php foreach($notifications as $notification): ?>
                        <?= $iconRenderer->fetch('icon.php', ['displayData' => $notification]) ?>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
    <div class="g-col-2 g-col-lg-1 box-sizing-border-box">
        <div class="jch-bs-card">
            <div class="jch-bs-card-header">
                <h2><span class="fa fa-tasks me-2"></span> <?= __('Utility Tasks', 'jch-optimize') ?></h2>
            </div>
            <div class="jch-bs-card-body">
                <nav class="jch-dash-icons px-3 pb-3">
                    <ul class="nav flex-wrap">
                    <?php
                        $buttons = $icons->compileUtilityIcons(
                            $icons->getUtilityArray(
                                ['browsercaching', 'orderplugins', 'keycache', 'recache', 'bulksettings', 'cleancache']
                            )
                        ) ?>
                    <?php foreach ($buttons as $button): ?>
                    <?= $iconRenderer->fetch('icon.php', ['displayData' => $button]) ?>
                    <?php endforeach; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
    <div class="g-col-2 g-col-lg-1 box-sizing-border-box">
        <div class="jch-bs-card">
            <div class="jch-bs-card-header">
                <h2><span class="fa fa-image me-2"></span> <?= __('Image/CDN Features', 'jch-optimize') ?></h2>
            </div>
            <div class="jch-bs-card-body">
                <nav class="jch-dash-icons px-3 pb-3">
                    <ul class="nav flex-wrap">
                        <?php $buttons = $icons->compileToggleFeaturesIcons($icons->getToggleSettings('images')); ?>
                        <?php foreach ($buttons as $button): ?>
                        <?= $iconRenderer->fetch('icon.php', ['displayData' => $button]) ?>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
    <div class="g-col-2 g-col-lg-1 box-sizing-border-box">
        <div class="jch-bs-card">
            <div class="jch-bs-card-header">
                <h2><span class="fa fa-users-cog me-2"></span> <?= __('Advanced Features', 'jch-optimize') ?></h2>
            </div>
            <div class="jch-bs-card-body">
                <nav class="jch-dash-icons px-3 pb-3">
                    <ul class="nav flex-wrap">
                        <?php $buttons = $icons->compileToggleFeaturesIcons($icons->getAdvancedToggleSettings()); ?>
                        <?php foreach ($buttons as $button): ?>
                        <?= $iconRenderer->fetch('icon.php', ['displayData' => $button]) ?>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
    <div class="g-col-2 g-col-lg-1 box-sizing-border-box">
        <div class="jch-bs-card">
            <div class="jch-bs-card-header">
                <h2><span class="fa fa-css3-alt me-2"></span> <?= __('CSS Features', 'jch-optimize') ?></h2>
            </div>
            <div class="jch-bs-card-body">
                <nav class="jch-dash-icons px-3 pb-3">
                    <ul class="nav flex-wrap">
                        <?php $buttons = $icons->compileToggleFeaturesIcons($icons->getToggleSettings('css')); ?>
                        <?php foreach ($buttons as $button): ?>
                            <?= $iconRenderer->fetch('icon.php', ['displayData' => $button]) ?>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
    <div class="g-col-2 g-col-lg-1 box-sizing-border-box">
        <div class="jch-bs-card">
            <div class="jch-bs-card-header">
                <h2><span class="fa fa-copyright me-2"></span> <?= __('Copyright Info') ?></h2>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item">
                   JCH Optimize Pro <?= JCH_VERSION ?> Copyright 2025 &copy; <a
                href="https://www.jch-optimize.net/">JCH Optimize</a>
                </li>
                <?php if(! JCH_PRO): ?>
                <li class="list-group-item">
                    <a href="https://www.jch-optimize.net/subscribes/subscribe-wordpress/new/wpstarter.html?layout=default&coupon=JCHGOPRO20">Upgrade
                        to the PRO version today</a> with 20% off using JCHGOPRO20
                </li>
        <?php else: ?>
    <li class="list-group-item">
        <div class="d-flex justify-content-between align-items-center">
           <div class="jchsupportinfo__optimize-desc">
               <span class="fa fa-cogs me-2"></span>
               Need help with configuring for best results? Check out our optimizing services.
           </div>
            <a href="https://www.jch-optimize.net/subscribes/subscribe-wordpress/wordpress-optimize/optimize-services-for-wordpress-article.html" class="btn btn-primary btn-sm" target="_blank">
                Get Help!
            </a>
        </div>
    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <script>
    (function () {
        const gridItems = document.querySelectorAll('div.g-col-2.g-col-lg-1');
        if (gridItems) {
            gridItems.forEach((item) => {
                const contentHeight = item.getBoundingClientRect().height + 16
                const rowSpan = Math.round(contentHeight/16);
                item.style.gridRowEnd = `span ${rowSpan}`;
            });
        }
    })();
    </script>
</div>
<div id="bulk-settings-modal-container" class="modal fade" tabindex="-1">
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