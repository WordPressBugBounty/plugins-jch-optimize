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

    defined( '_JCH_EXEC' ) or die( 'Restricted Access' );

?>

@section('browse-filters')
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
                    <?php if(!JCH_PRO): ?>
                        disabled
                    <?php endif; ?>
            >
                <span class="fa fa-refresh"></span> Recache
                <?php if(!JCH_PRO): ?>
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
                <i>Storage: <span class="badge bg-primary"><?=$adapter?></span> </i>
                <i class="ms-2">Http Request:
                    <?php if($httpRequest == 'yes'): ?>
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
            document.getElementById('filter_search').value = ''
            document.getElementById('filter_time-1').value = ''
            document.getElementById('filter_time-2').value = ''
            document.getElementById('filter_device').value = ''
            document.getElementById('filter_adapter').value = ''
            document.getElementById('filter_http-request').value = ''
          })
        </script>
    </div>

@stop

@section('browse-table-header')
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
            <?=__('Last modified time', 'jch-optimize')?>
        </th>
        <th scope="col">
            <?=__('Page URL', 'jch-optimize')?>
        </th>
        <th scope="col" class="text-center">
            <?=__('Device', 'jch-optimize')?>
        </th>
        <th scope="col">
            <?=__('Adapter', 'jch-optimize')?>
        </th>
        <th scope="col" class="text-center">
            <?=__('HTTP Request', 'jch-optimize')?>
        </th>
        <th scope="col" class="d-none d-sm-none d-md-none d-lg-table-cell">
            <?=__('Cache ID', 'jch-optimize')?>
        </th>
    </tr>

@stop

@section('browse-table-body-withrecords')
    <!--Table body when records are present -->
    <?php $i = 0; ?>
    <?php foreach($items as $item): ?>
        <tr>
            <td>
                <input type="checkbox" id="cb<?=$i++?>" name="cid[]" value="<?=$item['id']?>"
                       class="form-check-input">
            </td>
            <td>
                <?=date('l, F d, Y h:i:s A', $item['mtime'])?> GMT
            </td>
            <td>
                <a title="<?=$item['url']?>" href="<?=$item['url']?>" class="page-cache-url" target="_blank"><span class="fa fa-external-link"></span> <?=$item['url']?></a>
            </td>
            <td class="text-center">
                <?php if($item['device'] == 'Desktop'): ?>
                    <span class="fa fa-desktop" data-bs-toggle="tooltip" title="<?=$item['device']?>"
                          tabindex="0"></span>
                <?php else: ?>
                    <span class="fa fa-mobile-alt" data-bs-toggle="tooltip" title="<?=$item['device']?>"
                          tabindex="0"></span>
                <?php endif; ?>
            </td>
            <td>
                <?=$item['adapter']?>
            </td>
            <td style="text-align: center;">
                <?php if($item['http-request'] == 'yes'): ?>
                    <span class="fa fa-check-circle" style="color: green;"></span>
                <?php else: ?>
                    <span class="fa fa-times-circle" style="color: firebrick;"></span>
                <?php endif; ?>
            </td>
            <td class="d-none d-sm-none d-md-none d-lg-table-cell">
                <?=$item['id']?>
            </td>
        </tr>
    <?php endforeach; ?>
@stop

@section('browse-table-footer')
    <tr>
        <td colspan="99" class="center">

            <?php if($paginator->pageCount > 1 ): ?>
                <nav aria-label="pagination">
                    <ul class="pagination justify-content-center">
                        <!--Previous and start page link -->
                        <?php if(isset($paginator->previous)): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?=$pageLink?>&list_page=<?=$paginator->first?>">Start</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link"
                                   href="<?=$pageLink?>&list_page=<?=$paginator->previous?>">Previous</a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Start</a>
                            </li>

                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                            </li>
                        <?php endif; ?>

                        <!-- Numbered page links -->
                        <?php foreach($paginator->pagesInRange as $page): ?>
                            <?php if($page != $paginator->current): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?=$pageLink?>&list_page=<?=$page?>"><?=$page?></a>
                                </li>
                            <?php else: ?>
                                <li class="page-item active" aria-current="page">
                                    <a class="page-link" href="<?=$pageLink?>&list_page=<?=$page?>"><?=$page?></a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <!-- Next and last page link -->
                        <?php if(isset($paginator->next)): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?=$pageLink?>&list_page=<?=$paginator->next?>">Next</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link"
                                   href="<?=$pageLink?>&list_page=<?=$paginator->last?>">End</a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <a class="page-link" href="#">Next</a>
                            </li>
                            <li class="page-item disabled">
                                <a class="page-link" href="#">End</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>

            <?php endif; ?>

        </td>
    </tr>
@stop

@section('browse-hidden-fields')
    <!--Add these hidden fields to the default -->
    <input type="hidden" name="page" id="page" value="jch_optimize"/>
    <input type="hidden" name="tab" id="tab" value="pagecache"/>
    {!! wp_nonce_field('jch_pagecache'); !!}
@stop
