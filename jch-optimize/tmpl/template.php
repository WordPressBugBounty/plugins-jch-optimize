<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

defined('_JCH_EXEC') or die('Restricted Access');

$appName = JCH_PRO ? 'JCH Optimize Pro' : 'JCH Optimize';

?>

<div class="wrap">
    <h1><?= $appName; ?> Settings</h1>
    <nav class="nav-tab-wrapper">
        <a href="?page=jch_optimize" class="nav-tab <?= $tab == 'main' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Dashboard', 'jch-optimize'); ?>
        </a>
        <a href="?page=jch_optimize&tab=optimizeimages"
           class="nav-tab <?= $tab == 'optimizeimages' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Optimize Images', 'jch-optimize'); ?>
        </a>
        <a href="?page=jch_optimize&tab=pagecache"
           class="nav-tab <?= $tab == 'pagecache' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Page Cache', 'jch-optimize'); ?>
        </a>
        <a href="?page=jch_optimize&tab=configurations"
           class="nav-tab <?= $tab == 'configurations' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Configurations', 'jch-optimize'); ?>
        </a>
        <a href="?page=jch_optimize&tab=help" class="nav-tab <?= $tab == 'help' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Help', 'jch-optimize'); ?>
        </a>
    </nav>
    <div class="tab-content pt-5">
        <?=$content?>
    </div>
</div>
