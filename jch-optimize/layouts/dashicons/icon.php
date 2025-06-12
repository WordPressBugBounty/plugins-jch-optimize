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
        <ul class="list-unstyled d-flex w-100 h-100 p-0">
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
                                  <span><?= $displayData['name']; ?>

                                      <?php
                                        if (!empty($displayData['tooltip'])) :
                                            ?>
                                          <span class="hasPopover ms-2"
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
            <li class="dashicon-end align-items-start pe-2 pb-1">
                <div class="dashicon-configure align-self-end h-25">
                    <?php
                    if (!empty($displayData['configure'])) :
                        ?>
                        <div class="fa fa-ellipsis-v"></div>
                        <?php
                    endif;
                    ?>
                </div>
                <div class="dashicon-toggle align-self-center fs-1 h-50 d-flex align-items-center">
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
                        <span class="fs-6"><span class="fa fa-ban mx-1"></span>Pro</span>
                        <?php
                    endif;
                    ?>
                </div>
            </li>
        </ul>
    </a>
</li>