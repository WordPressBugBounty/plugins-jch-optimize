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

?>
<!--Table body when records are present -->
<?php $i = 0; ?>
<?php foreach ($items as $item): ?>
    <tr>
        <td>
            <input type="checkbox" id="cb<?= $i++ ?>" name="cid[]" value="<?= $item['id'] ?>"
                   class="form-check-input">
        </td>
        <td>
            <?= date('l, F d, Y h:i:s A', $item['mtime']) ?> GMT
        </td>
        <td>
            <a title="<?= $item['url'] ?>" href="<?= $item['url'] ?>" class="page-cache-url" target="_blank"><span
                        class="fa fa-external-link"></span> <?= $item['url'] ?></a>
        </td>
        <td class="text-center">
            <?php if ($item['device'] == 'Desktop'): ?>
                <span class="fa fa-desktop" data-bs-toggle="tooltip" title="<?= $item['device'] ?>"
                      tabindex="0"></span>
            <?php else: ?>
                <span class="fa fa-mobile-alt" data-bs-toggle="tooltip" title="<?= $item['device'] ?>"
                      tabindex="0"></span>
            <?php endif; ?>
        </td>
        <td>
            <?= $item['adapter'] ?>
        </td>
        <td style="text-align: center;">
            <?php if ($item['http-request'] == 'yes'): ?>
                <span class="fa fa-check-circle" style="color: green;"></span>
            <?php else: ?>
                <span class="fa fa-times-circle" style="color: firebrick;"></span>
            <?php endif; ?>
        </td>
        <td class="d-none d-sm-none d-md-none d-lg-table-cell">
            <?= $item['id'] ?>
        </td>
    </tr>
<?php endforeach; ?>
