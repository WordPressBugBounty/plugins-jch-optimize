<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

/**
 * @var array $displayData
 */
$showProOnly = false;
if (!JCH_PRO && !empty($displayData['proonly'])) :
    $displayData['link'] = '';
    $displayData['script'] = ' onclick="return false;"';
    $displayData['class'] = ['disabled', 'pro-only'];
    $showProOnly = true;
endif;
?>
<li id="<?= $displayData['id']; ?>" class="dashicon-wrapper <?= implode(' ', $displayData['class']) ?? ''; ?>">
    <a class="w-100 dashicon"
       href="<?= $displayData['link'] ?: '#' ?>" <?= $displayData['script'] ?? ''; ?>>
        <ul class="list-unstyled d-flex w-100 h-100 p-0 position-relative overflow-visible">
            <li class="dashicon-start">
                <div class="dashicon-inner ps-3">
                    <div class="dashicon-info">
                        <div class="dashicon-icon">
                            <div class="<?= $displayData['icon']; ?>"></div>
                        </div>
                        <?php
                        if (!empty($displayData['details'])) :
                            ?>
                            <div class="dashicon-details">
                                <?= $displayData['details'] ?>
                            </div>
                            <?php
                        endif;
                        ?>

                    </div>
                    <div class="dashicon-title">
                                  <span class="d-inline-flex align-items-center gap-1 flex-nowrap text-nowrap overflow-visible me-5"><?= $displayData['name']; ?>

                                      <?php
                                        if (!empty($displayData['tooltip'])) :
                                            ?>
                                          <span class="hasPopover ms-2 d-inline-flex align-items-center"
                                                data-bs-content="<?= $displayData['tooltip']; ?>"
                                                data-bs-original-title="<?= $displayData['name']; ?>">
                                                <div class="far fa-question-circle"> </div>
                                            </span>
                                            <?php
                                        endif;
                                        ?>
                                  </span>
                    </div>
                </div>
            </li>
            <li class="dashicon-end pe-2 pb-1 position-absolute end-0 top-50 translate-middle-y">
                <div class="dashicon-configure align-self-end h-25">
                    <?php
                    if (!empty($displayData['configure'])) :
                        ?>
                        <div class="fa fa-ellipsis-v"></div>
                        <?php
                    endif;
                    ?>
                </div>
                <div class="dashicon-toggle d-inline-flex align-items-center flex-nowrap text-nowrap overflow-visible">
                    <?php
                    if (!$showProOnly && isset($displayData['enabled'])) :
                        $state = $displayData['enabled'] ? 'on' : 'off';
                        ?>
                        <div class="fs-6 fa fa-toggle-<?= $state; ?>"></div>
                        <?php
                    endif;
                    ?>
                    <?php
                    if ($showProOnly) :
                        ?>
                        <small class="d-inline-flex align-items-center gap-1 text-nowrap">
                            <span class="fa fa-ban"></span>
                            <span class="text-small">Pro</span></small>
                        <?php
                    endif;
                    ?>
                </div>
            </li>
        </ul>
    </a>
</li>