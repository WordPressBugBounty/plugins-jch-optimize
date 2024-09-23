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

namespace JchOptimize\Controller;

use JchOptimize\Core\Input;
use JchOptimize\Core\Laminas\ArrayPaginator;
use JchOptimize\Core\Mvc\Controller;
use JchOptimize\Log\WordpressNoticeLogger;
use JchOptimize\Model\PageCache as PageCacheModel;
use JchOptimize\Model\ReCache;
use JchOptimize\Platform\Plugin;
use JchOptimize\Plugin\Admin;
use JchOptimize\View\PageCacheHtml;

use function __;
use function check_admin_referer;
use function wp_redirect;

class PageCache extends Controller
{
    /**
     * @var PageCacheHtml
     */
    private PageCacheHtml $view;

    /**
     * @var PageCacheModel
     */
    private PageCacheModel $model;

    public function __construct(PageCacheHtml $view, PageCacheModel $model, ?Input $input = null)
    {
        $this->view = $view;
        $this->model = $model;

        parent::__construct($input);
    }

    /**
     * @inheritDoc
     */
    public function execute(): bool
    {
        /** @var WordpressNoticeLogger $logger */
        $logger = $this->logger;
        /** @var Input $input */
        $input = $this->getInput();

        if ($input->get('action') !== null) {
            check_admin_referer('jch_pagecache');
        }

        if ($input->get('action') == 'delete') {
            if ($input->get('cid')) {
                $success = $this->model->delete((array)$input->get('cid'));
            } else {
                $success = false;
            }
        }

        if ($input->get('action') == 'deleteall') {
            $success = $this->model->deleteAll();
        }

        if (JCH_PRO && $input->get('action') == 'recache') {
            /** @var ReCache $reCacheModel */
            $reCacheModel = $this->getContainer()->get(ReCache::class);
            $reCacheModel->reCache('options-general.php?page=jch_optimize&tab=pagecache');
        }

        if (isset($success)) {
            if ($success) {
                $logger->success(__('Page cache deleted successfully.'));
            } else {
                $logger->error(__('Error deleting page cache.'));
            }

            wp_redirect('options-general.php?page=jch_optimize&tab=pagecache');
            exit();
        }

        if (!Plugin::getPluginParams()->get('cache_enable', '0')) {
            $logger->warning(
                __(
                    'Page Cache is not enabled. Please enable it on the Dashboard or Configurations tab. You may also want to disable other page cache plugins.'
                )
            );
            Admin::publishAdminNotices();
        }

        $limit = (int)$this->model->getState()->get('list_limit', '20');
        $page = (int)$input->get('list_page', '1');

        $paginator = new ArrayPaginator($this->model->getItems());
        $paginator->setCurrentPageNumber($page)
                  ->setItemCountPerPage($limit);

        $this->view->setData([
            'items'       => $paginator,
            'tab'         => 'pagecache',
            'paginator'   => $paginator->getPages(),
            'pageLink'    => 'options-general.php?page=jch_optimize&tab=pagecache',
            'adapter'     => $this->model->getAdapterName(),
            'httpRequest' => $this->model->isCaptureCacheEnabled()
        ]);

        $this->view->loadResources();
        $this->view->renderStatefulElements($this->model->getState());

        echo $this->view->render();

        return true;
    }
}
