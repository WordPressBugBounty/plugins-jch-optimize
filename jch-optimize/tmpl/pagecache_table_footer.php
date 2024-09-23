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

<tr>
    <td colspan="99" class="center">

        <?php if ($paginator->pageCount > 1): ?>
            <nav aria-label="pagination">
                <ul class="pagination justify-content-center">
                    <!--Previous and start page link -->
                    <?php if (isset($paginator->previous)): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $pageLink ?>&list_page=<?= $paginator->first ?>">Start</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link"
                               href="<?= $pageLink ?>&list_page=<?= $paginator->previous ?>">Previous</a>
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
                    <?php foreach ($paginator->pagesInRange as $page): ?>
                        <?php if ($page != $paginator->current): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= $pageLink ?>&list_page=<?= $page ?>"><?= $page ?></a>
                            </li>
                        <?php else: ?>
                            <li class="page-item active" aria-current="page">
                                <a class="page-link" href="<?= $pageLink ?>&list_page=<?= $page ?>"><?= $page ?></a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <!-- Next and last page link -->
                    <?php if (isset($paginator->next)): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $pageLink ?>&list_page=<?= $paginator->next ?>">Next</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link"
                               href="<?= $pageLink ?>&list_page=<?= $paginator->last ?>">End</a>
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
