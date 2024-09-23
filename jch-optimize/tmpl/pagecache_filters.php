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

<!-- Toolbar -->
<div class="btn-toolbar justify-content-between mb-3" role="toolbar">
    <div class="btn-toolbar">
        <button id="delete-button" type="submit" name="action" value="delete" class="btn btn-dark">
            <span class="fa fa-trash"></span> Delete
        </button>
        <button id="deleteall-button" type="submit" name="action" value="deleteall" class="btn btn-secondary ms-1">
            <span class="fa fa-trash-alt"></span> Delete all
        </button>
        <button id="recache-button" type="submit" name="action" value="recache" class="btn btn-outline-dark ms-1"
            <?php if (!JCH_PRO): ?>
                disabled
            <?php endif; ?>
        >
            <span class="fa fa-refresh"></span> Recache
            <?php if (!JCH_PRO): ?>
                <span style="font-size: 0.6em; display: inline; padding-left: 5px;"><span class="fa fa-lock"></span> Pro</span>
            <?php endif; ?>
        </button>
        <script>
            document.getElementById('delete-button').addEventListener('click', function (event) {
                if (!document.querySelectorAll('input[name="cid[]"]:checked').length) {
                    alert('Please select an item')
                    event.preventDefault()
                }
            })
        </script>
    </div>
    <div class="btn-toolbar">
        <div class="d-flex align-items-center">
            <i>Storage: <span class="badge bg-primary"><?= $adapter ?></span> </i>
            <i class="ms-2">Http Request:
                <?php if ($httpRequest == 'yes'): ?>
                    <span class="badge bg-success">On</span>
                <?php else: ?>
                    <span class="badge bg-danger">Off</span>
                <?php endif; ?>
            </i>
        </div>
        <select id="list_fullordering" name="list[fullordering]" class="ms-2" onchange="this.form.submit();"
                aria-label="Order by list">
            <?= $orderOptionsHtml ?>
        </select>

        <select id="list_limit" name="list[limit]" class="ms-2" onchange="this.form.submit();"
                aria-label="List limit">
            <?= $limitOptionsHtml ?>
        </select>
    </div>
</div>
<!--Filters -->
<div class="btn-toolbar justify-content-end mb-4" role="toolbar">
    <div class="input-group">
        <?= $searchInput ?>
        <button type="submit" class="btn btn-outline-secondary" id="filter-search-button">Search</button>
    </div>
    <?= $filterTime1SelectHtml ?>
    <?= $filterTime2SelectHtml ?>
    <?= $filterDeviceSelectHtml ?>
    <?= $filterAdapterSelectHtml ?>
    <?= $filterHttpRequestSelectHtml ?>
    <button id="clear-search" type="submit" class="btn btn-secondary ms-2">Clear Filters</button>
    <script>
        document.getElementById('clear-search').addEventListener('click', function (event) {
            document.getElementById('filter_search').value = '';
            document.getElementById('filter_time-1').value = '';
            document.getElementById('filter_time-2').value = '';
            document.getElementById('filter_device').value = '';
            document.getElementById('filter_adapter').value = '';
            document.getElementById('filter_http-request').value = '';
            document.getElementById('list_limit').value = '';
            document.getElementById('list_fullordering').value = '';
        })
    </script>
</div>
