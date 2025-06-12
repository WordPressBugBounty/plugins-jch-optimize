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
use JchOptimize\WordPress\Log\WordpressNoticeLogger;
use JchOptimize\WordPress\View\OptimizeImageHtml;

use function __;
use function is_null;
use function sprintf;
use function wp_redirect;

class OptimizeImages extends Controller
{
    public function __construct(private OptimizeImageHtml $view, private Icons $icons, ?Input $input)
    {
        parent::__construct($input);
    }

    public function execute(): bool
    {
        /** @var Input $input */
        $input = $this->getInput();
        /** @var WordpressNoticeLogger $logger */
        $logger = $this->logger;
        /** @var string|null $status */
        $status = $input->get('status');

        if (is_null($status)) {
            $this->view->loadResources();
            $this->view->setData([
                'tab'   => 'optimizeimages',
                'icons' => $this->icons
            ]);

            echo $this->view->render();
        } else {
            if ($status == 'success') {
                $cnt = $input->getString('cnt');
                $webp = $input->getString('webp');

                $logger->success(sprintf(__('%1$d images successfully optimized, %2$s WEBPs generated.', 'jch-optimize'), $cnt, $webp));
            } else {
                $msg = $input->getString('msg');
                $logger->error(__('The Optimize Image function failed with message "' . $msg, 'jch-optimize'));
            }

            wp_redirect('options-general.php?page=jch_optimize&tab=optimizeimages');
        }

        return true;
    }
}
