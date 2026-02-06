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

namespace JchOptimize\WordPress\Controller;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Joomla\Input\Input;
use JchOptimize\Core\Laminas\ArrayPaginator;
use JchOptimize\Core\Mvc\Controller;
use JchOptimize\Core\Registry;
use JchOptimize\WordPress\Contracts\WillEnqueueAssets;
use JchOptimize\WordPress\Log\WordpressNoticeLogger;
use JchOptimize\WordPress\Model\PageCache as PageCacheModel;
use JchOptimize\WordPress\Model\ReCache;
use JchOptimize\WordPress\Plugin\Admin;
use JchOptimize\WordPress\View\PageCacheHtml;

use function __;
use function check_admin_referer;
use function wp_redirect;

class PageCache extends Controller implements WillEnqueueAssets
{
    private ?ReCache $reCache = null;

    public function __construct(
        private Registry $params,
        private PageCacheHtml $view,
        private PageCacheModel $model,
        Container $container,
        ?Input $input = null
    ) {
        parent::__construct($input);

        $this->setContainer($container);

        if (JCH_PRO) {
            $this->reCache = $this->getContainer()->get(ReCache::class);
        }
    }

    private string $redirect = 'options-general.php?page=jch_optimize&tab=pagecache';

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
            $this->reCache->runOnce();
            $logger->success(__('ReCache successfully started.'));

            wp_redirect($this->redirect);
            exit();
        }

        if (isset($success)) {
            if ($success) {
                $logger->success(__('Page cache deleted successfully.'));
            } else {
                $logger->error(__('Error deleting page cache.'));
            }

            wp_redirect($this->redirect);
            exit();
        }

        if (!$this->params->get('cache_enable', '0')) {
            $logger->warning(
                __(
                    'Page Cache is not enabled. Please enable it on the Dashboard or Configurations tab.'
                    . ' You may also want to disable other page cache plugins.'
                )
            );
            Admin::publishAdminNotices();
        }

        $this->model->updateHtaccess();

        $limit = (int)$this->model->getState()->get('list_limit', '20');
        $page = (int)$input->get('list_page', '1');

        $paginator = new ArrayPaginator($this->model->getItems());
        $paginator->setCurrentPageNumber($page)
                  ->setItemCountPerPage($limit);

        $this->view->setData([
            'items'       => $paginator,
            'tab'         => 'pagecache',
            'paginator'   => $paginator->getPages(),
            'pageLink'    => $this->redirect,
            'action'      => $this->redirect,
            'adapter'     => $this->model->getAdapterName(),
            'httpRequest' => $this->model->isCaptureCacheEnabled(),
        ]);

        $this->view->renderStatefulElements($this->model->getState());

        echo $this->view->render();

        return true;
    }

    public function setRedirect(string $redirect): PageCache
    {
        $this->redirect = $redirect;

        return $this;
    }

    public function setLayout(string $layout): PageCache
    {
        $this->view->getRenderer()->getRenderer()->setLayout($layout);

        return $this;
    }

    public function enqueueAssets(): void
    {
        $this->view->loadResources();
    }
}
