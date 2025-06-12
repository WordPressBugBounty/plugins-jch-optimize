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

class DeleteBackups extends Controller
{
    public function __construct(private AdminTasks $tasks, ?Input $input = null)
    {
        parent::__construct($input);
    }

    public function execute(): bool
    {
        /** @var WordpressNoticeLogger $logger */
        $logger = $this->logger;
        $mResult = $this->tasks->deleteBackupImages();

        if ($mResult === false) {
            $logger->error(__('Failed trying to delete backup images', 'jch-optimize'));

            $result = false;
        } elseif ($mResult === 'BACKUPPATHDOESNTEXIST') {
            $logger->warning(
                __(
                    'The folder containing backup images wasn\'t created yet. Try optimizing some images first.',
                    'jch-optimize'
                )
            );

            $result = false;
        } else {
            $logger->success(__('Successfully deleted backup images', 'jch-optimize'));

            $result = true;
        }

        wp_redirect('options-general.php?page=jch_optimize&tab=optimizeimages');

        return $result;
    }
}
