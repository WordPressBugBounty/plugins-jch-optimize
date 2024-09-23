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

<!-- Header row -->
<tr>
    <th scope="col">
        <input type="checkbox" class="form-check-input" id="check-all" data-bs-toggle="tooltip"
               title="Select all items">
        <script>
            document.getElementById('check-all').addEventListener('click', function (event) {
                const checkboxes = document.querySelectorAll('input[name="cid[]"]')
                if (this.checked) {
                    checkboxes.forEach((checkbox) => {
                        checkbox.checked = 'checked'
                    })
                } else {
                    checkboxes.forEach((checkbox) => {
                        checkbox.checked = ''
                    })
                }

            })
        </script>
    </th>
    <th scope="col">
        <?= __('Last modified time', 'jch-optimize') ?>
    </th>
    <th scope="col">
        <?= __('Page URL', 'jch-optimize') ?>
    </th>
    <th scope="col" class="text-center">
        <?= __('Device', 'jch-optimize') ?>
    </th>
    <th scope="col">
        <?= __('Adapter', 'jch-optimize') ?>
    </th>
    <th scope="col" class="text-center">
        <?= __('HTTP Request', 'jch-optimize') ?>
    </th>
    <th scope="col" class="d-none d-sm-none d-md-none d-lg-table-cell">
        <?= __('Cache ID', 'jch-optimize') ?>
    </th>
</tr>
