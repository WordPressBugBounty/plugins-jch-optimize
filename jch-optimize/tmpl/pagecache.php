<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/worpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

defined('_JCH_EXEC') or die('Restricted Access');


?>
<!-- Administrator form for browse views -->
<form action="options-general.php?page=jch_optimize&tab=pagecache" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <!-- Filters and ordering -->
        <?= $this->fetch('pagecache_filters.php', $data); ?>

        <?php if (!count($items)): ?>
            <div class="alert alert-light ">
                <span class="fa fa-info-circle"></span>
                <?= __('There are no records currently available for viewing.') ?>
            </div>
        <?php else: ?>

            <table class="table table-striped table-hover" id="itemsList">
                <thead>
                <?= $this->fetch('pagecache_table_header.php', $data); ?>
                </thead>
                <tfoot>
                <?= $this->fetch('pagecache_table_footer.php', $data); ?>
                </tfoot>
                <tbody>
                <?= $this->fetch('pagecache_body_with_records.php', $data); ?>
                </tbody>
            </table>

        <?php endif; ?>

        <div>
            <input type="hidden" name="page" id="page" value="jch_optimize"/>
            <input type="hidden" name="tab" id="tab" value="pagecache"/>
            <?php wp_nonce_field('jch_pagecache'); ?>
        </div>
    </div>
</form>
