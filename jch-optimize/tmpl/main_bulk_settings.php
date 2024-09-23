<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/joomla-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2020 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

defined('_JCH_EXEC') or die('Restricted Access');

$options = [
        'task' => 'importsettings'
];
$data = json_encode($options);
$maxFileSize = ini_get('upload_max_filesize');
$confirmDelete = __('Are you sure you want to reset all your settings? Make sure you export them first in case you change your mind.', 'jch-optimize');
$pageUrl = add_query_arg(['page'=>'jch_optimize'], admin_url('options-general.php'));

?>
<form id="bulk-settings-form" action="<?=$pageUrl?>" name="bulk-settings-form"
      method="post"
      enctype="multipart/form-data">
    <p class="alert alert-warning"><?=__('Importing settings from another website may not always work perfectly, even if the site is similar. Some modifications may be necessary.', 'jch-optimize')?></p>
    <p class="text-center">
        <button id="export-settings-file-button" type="submit" class="btn btn-secondary" name="task"
                value="exportsettings">
            <span class="fa fa-download"></span>
            <?=__('Export settings', 'jch-optimize')?>
        </button>
        <button id="reset-settings-button" type="submit" class="btn btn-warning"
                name="task" value="setdefaultsettings" onclick="return confirm('<?=$confirmDelete?>')">
            <span class="fa fa-redo-alt"></span>
            <?=__('Reset default settings', 'jch-optimize')?>
        </button>
        <button id="import-settings-file-button" type="button" class="btn btn-primary"
                onclick="getSettingsFileUpload()" name="task" value="importsettings">
            <span class="fa fa-upload"></span>
            <?=__('Import settings', 'jch-optimize')?>
        </button>
    <div class="hidden">
      <!--  <input type="hidden" name="MAX_FILE_SIZE" value="4000"> -->
        <input id="bulk-settings-file-input" type="file" name="file" accept="application/json">
        <?php wp_nonce_field('jch_bulksettings') ?>
    </div>
    </p>
</form>
