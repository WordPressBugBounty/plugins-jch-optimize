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

    defined( '_JCH_EXEC' ) or die( 'Restricted Access' );

    use JchOptimize\Html\TabContent;
    use JchOptimize\Platform\Plugin;

    $oParams            = Plugin::getPluginParams();
    $smartCombineValues = $oParams->get( 'pro_smart_combine_values', '' );

?>

    <form action="options.php" method="post" id="jch-optimize-settings-form">
        <div class="grid mt-n3">
            <div class="g-col-12 g-col-md-2">
                <ul class="nav flex-wrap flex-md-column nav-pills">
                    <li class="nav-item">
                        <a class="nav-link active" href="#general-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?=__('General', 'jch-optimize')?></div>
                                <small class="text-wrap d-none d-lg-block"><?=__('Download ID, Exclude menus, Combine files', 'jch-optimize')?></small>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#combine-files-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?=__('Combine Files', 'jch-optimize')?></div>
                                <small class="text-wrap d-none d-lg-block"><?=__('Smart Combine, Files delivery, Minify HTML level')?></small>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#css-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?=__('CSS', 'jch-optimize')?></div>
                                <small class="text-wrap d-none d-lg-block"><?=__('Exclude CSS, Google fonts, Optimize CSS delivery, Reduce unused CSS', 'jch-optimize')?></small>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#javascript-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?=__('JavaScript', 'jch-optimize')?></div>
                                <small class="text-wrap d-none d-lg-block"><?=__('Optimize JS, Exclude JS, Don\'t move to bottom, Remove JS', 'jch-optimize')?></small>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#page-cache-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?=__('Page Cache', 'jch-optimize')?></div>
                                <small class="text-wrap d-none d-lg-block"><?=__('Mobile caching, Cache lifetime, Exclude urls', 'jch-optimize')?></small>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#media-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?=__('Media', 'jch-optimize')?></div>
                                <small class="text-wrap d-none d-lg-block"><?=__('Lazy-load, Add image attributes, Sprite generator', 'jch-optimize')?></small>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#preloads-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?=__('Preloads', 'jch-optimize')?></div>
                                <small class="text-wrap d-none d-lg-block"><?=__('Http/2 preload, Optimize fonts', 'jch-optimize')?></small>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#cdn-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?=__('CDN', 'jch-optimize')?></div>
                                <small class="text-wrap d-none d-lg-block"><?=__('Preconnect domains, Select file types, 3 Domains', 'jch-optimize')?></small>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#optimize-image-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?=__('Optimize Images', 'jch-optimize')?></div>
                                <small class="text-wrap d-none d-lg-block"><?=__('Webp generation, Optimize by page, Optimize by folders', 'jch-optimize')?></small>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#miscellaneous-tab" data-bs-toggle="tab">
                            <div>
                                <div class="fs-6 fw-bold mb-1"><?=__('Misc', 'jch-optimize')?><span
                                            class="d-md-none d-lg-inline"><?=__('ellaneous', 'jch-optimize')?></span>
                                </div>
                                <small class="text-wrap d-none d-lg-block"><?=__('Reduce DOM, Mode Switcher', 'jch-optimize')?></small>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="g-col-12 g-col-md-10">
                <?= TabContent::start() ?>

                <?= settings_fields('jchOptimizeOptionsPage') ?>
                <?= do_settings_sections('jchOptimizeOptionsPage') ?>

                <?= TabContent::end() ?>

                <input type="hidden" id="jch-optimize_settings_pro_smart_combine_values"
                       name="jch-optimize_settings[pro_smart_combine_values]" class="jch-smart-combine-values"
                       value="<?=$smartCombineValues?>">
                <input type="hidden" id="jch-optimize_settings_hidden_api_secret"
                       name="jch-optimize_settings[hidden_api_secret]"
                       value="11e603aa">

                <?= submit_button('Save Settings', 'primary large', 'jch-optimize_settings_submit') ?>
            </div>
        </div>
    </form>