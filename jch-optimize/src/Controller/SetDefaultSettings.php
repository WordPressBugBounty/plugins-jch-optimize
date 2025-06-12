<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\WordPress\Controller;

use JchOptimize\Core\Mvc\Controller;
use JchOptimize\WordPress\Log\WordpressNoticeLogger;
use JchOptimize\WordPress\Model\BulkSettings;

use function __;
use function check_admin_referer;
use function wp_redirect;

class SetDefaultSettings extends Controller
{
    public function __construct(private BulkSettings $bulkSettings)
    {
        parent::__construct();
    }

    public function execute(): bool
    {
        check_admin_referer('jch_bulksettings');

        /** @var WordpressNoticeLogger $logger */
        $logger = $this->logger;
        if ($this->bulkSettings->setDefaultSettings() !== false) {
            $logger->success(__('Successfully restored default settings', 'jch-optimize'));
        } else {
            $logger->error(__('Failed restoring default settings', 'jch-optimize'));
        }

        wp_redirect('options-general.php?page=jch_optimize');

        return true;
    }
}
