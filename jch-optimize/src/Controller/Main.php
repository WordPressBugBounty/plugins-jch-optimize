<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2020 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\WordPress\Controller;

use _JchOptimizeVendor\V91\Joomla\Input\Input;
use JchOptimize\Core\Admin\Icons;
use JchOptimize\Core\Mvc\Controller;
use JchOptimize\Core\PageCache\CaptureCache;
use JchOptimize\WordPress\Contracts\WillEnqueueAssets;
use JchOptimize\WordPress\Model\NotificationIcons;
use JchOptimize\WordPress\View\MainHtml;

class Main extends Controller implements WillEnqueueAssets
{
    public function __construct(
        private MainHtml $view,
        private Icons $icons,
        private NotificationIcons $notificationIcons,
        ?Input $input
    ) {
        parent::__construct($input);
    }

    public function execute(): bool
    {
        if (JCH_PRO) {
            $this->getContainer()->get(CaptureCache::class)->updateHtaccess();
        }

        $this->view->setData([
            'tab'           => 'main',
            'icons'         => $this->icons,
            'notifications' => $this->notificationIcons->getNotificationIcons(),
        ]);

        echo $this->view->render();

        return true;
    }

    public function enqueueAssets(): void
    {
        $this->view->loadResources();
    }
}
