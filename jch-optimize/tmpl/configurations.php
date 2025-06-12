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
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Platform\PluginInterface;
use JchOptimize\Core\SystemUri;
use JchOptimize\WordPress\Html\TabContent;

/**
 * @var PathsInterface $pathsUtils
 * @var PluginInterface $plugin
 */

$modalData = [
    'baseUrl' => SystemUri::siteBaseFull($pathsUtils),
    'loadingImageUrl' => $pathsUtils->mediaUrl() . '/core/images/loader.gif',
    'tableBodyAjaxUrl' => admin_url('admin-ajax.php') . '?action=jch_configure_js_table_body',
    'autoSaveAjaxUrl' => admin_url('admin-ajax.php') . '?action=jch_configure_js_auto_save',
];
/** @var PhpRenderer $modalRenderer */
$modalRenderer = clone $this;
$modalRenderer->setTemplatePath(JCH_PLUGIN_DIR . 'layouts/configure_helper');

?>

<form action="options.php" method="post" id="jch-optimize-settings-form">
    <div class="jch-bs-container-fluid box-sizing-border-box mt-n3">
        <div class="row box-sizing-border-box">
            <div id="settings-navigation" class="col-12 col-md-2 box-sizing-border-box">
                <ul class="nav flex-wrap flex-md-column nav-pills">
                    <li class="nav-item">
                        <a class="nav-link active" href="#general-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?= __('General', 'jch-optimize') ?></div>
                                <small class="text-wrap d-none d-lg-block"><?= __(
                                    'Download ID, Exclude menus, Combine files',
                                    'jch-optimize'
                                ) ?></small>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#combine-files-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?= __('Optimize Files', 'jch-optimize') ?></div>
                                <small class="text-wrap d-none d-lg-block"><?= __(
                                    'Smart Combine, Files delivery, Minify HTML level'
                                ) ?></small>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#css-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?= __('CSS', 'jch-optimize') ?></div>
                                <small class="text-wrap d-none d-lg-block"><?= __(
                                    'Exclude CSS, Google fonts, Optimize CSS delivery, Reduce unused CSS',
                                    'jch-optimize'
                                ) ?></small>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#javascript-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?= __('JavaScript', 'jch-optimize') ?></div>
                                <small class="text-wrap d-none d-lg-block"><?= __(
                                    'Optimize JS, Exclude JS, Don\'t move to bottom, Remove JS',
                                    'jch-optimize'
                                ) ?></small>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#page-cache-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?= __('Page Cache', 'jch-optimize') ?></div>
                                <small class="text-wrap d-none d-lg-block"><?= __(
                                    'Mobile caching, Cache lifetime, Exclude urls',
                                    'jch-optimize'
                                ) ?></small>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#media-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?= __('Media', 'jch-optimize') ?></div>
                                <small class="text-wrap d-none d-lg-block"><?= __(
                                    'Lazy-load, Add image attributes, Sprite generator',
                                    'jch-optimize'
                                ) ?></small>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#preloads-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?= __('Preloads', 'jch-optimize') ?></div>
                                <small class="text-wrap d-none d-lg-block"><?= __(
                                    'Http/2 preload, Optimize fonts',
                                    'jch-optimize'
                                ) ?></small>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#cdn-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?= __('CDN', 'jch-optimize') ?></div>
                                <small class="text-wrap d-none d-lg-block"><?= __(
                                    'Preconnect domains, Select file types, 3 Domains',
                                    'jch-optimize'
                                ) ?></small>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#optimize-image-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?= __('Optimize Images', 'jch-optimize') ?></div>
                                <small class="text-wrap d-none d-lg-block"><?= __(
                                    'Webp generation, Optimize by page, Optimize by folders',
                                    'jch-optimize'
                                ) ?></small>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#miscellaneous-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?= __('Misc', 'jch-optimize') ?><span
                                            class="d-md-none d-lg-inline"><?= __('ellaneous', 'jch-optimize') ?></span>
                                </div>
                                <small class="text-wrap d-none d-lg-block"><?= __(
                                    'Reduce DOM, Mode Switcher',
                                    'jch-optimize'
                                ) ?></small>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>
            <div id="settings-content" class="col-12 col-md-10 box-sizing-border-box position-relative">
                <?= TabContent::start() ?>

                <?php settings_fields('jchOptimizeOptionsPage') ?>
                <?php do_settings_sections('jchOptimizeOptionsPage') ?>

                <?= TabContent::end() ?>

                <?= $modalRenderer->fetch('critical_js_configure_helper.php', $modalData) ?>
                <input type="hidden" id="jch-optimize_settings_hidden_api_secret"
                       name="jch-optimize_settings[hidden_api_secret]"
                       value="11e603aa">

                <div id="sticky-submit-button" class="position-sticky bottom-0 pb-1 px-4 border-top border-5" style="background-color: #f0f0f1; z-index: 100;">
                <?php submit_button('Save Settings', 'primary large', 'jch-optimize_settings_submit') ?>
                </div>
                <div id="sticky-overlap-sentinel" class="position-absolute bottom-0 start-0 w-100 pe-none" style="height: 1px"></div>
            </div>
        </div>
    </div>
</form>