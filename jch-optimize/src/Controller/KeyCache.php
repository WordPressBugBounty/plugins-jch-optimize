<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2021 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\WordPress\Controller;

use _JchOptimizeVendor\V91\Joomla\Input\Input;
use JchOptimize\Core\Admin\AdminTasks;
use JchOptimize\Core\Mvc\Controller;
use JchOptimize\WordPress\Log\WordpressNoticeLogger;

use function __;
use function wp_redirect;

class KeyCache extends Controller
{
    public function __construct(private AdminTasks $tasks, ?Input $input = null)
    {
        parent::__construct($input);
    }

    public function execute(): bool
    {
        $this->tasks->generateNewCacheKey();

        /** @var WordpressNoticeLogger $logger */
        $logger = $this->logger;
        $logger->success(__('New cache key generated!', 'jch-optimize'));

        wp_redirect('options-general.php?page=jch_optimize');

        return true;
    }
}
