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

class BrowserCaching extends Controller
{
    public function __construct(private AdminTasks $tasks, ?Input $input = null)
    {
        parent::__construct($input);
    }

    public function execute(): bool
    {
        /** @var WordpressNoticeLogger $logger */
        $logger = $this->logger;
        $success = null;
        $expires = $this->tasks->leverageBrowserCaching($success);

        if ($success === false) {
            $logger->error(
                __('Failed trying to add browser caching codes to the .htaccess file', 'jch-optimize')
            );
            $result = false;
        } elseif ($expires === 'FILEDOESNTEXIST') {
            $logger->warning(__('No .htaccess file were found in the root of this site', 'jch-optimize'));
            $result = false;
        } elseif ($expires === 'CODEUPDATEDSUCCESS') {
            $logger->success(__('The .htaccess file was updated successfully', 'jch-optimize'));
            $result = true;
        } elseif ($expires === 'CODEUPDATEDFAIL') {
            $logger->error(__('Failed to update the .htaccess file', 'jch-optimize'));
            $result = false;
        } else {
            $logger->success(
                __('Successfully added codes to the .htaccess file to promote browser caching', 'jch-optimize')
            );
            $result = true;
        }

        wp_redirect('options-general.php?page=jch_optimize');

        return $result;
    }
}
