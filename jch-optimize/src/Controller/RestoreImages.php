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

namespace JchOptimize\Controller;

use JchOptimize\Core\Admin\Tasks;
use JchOptimize\Core\Mvc\Controller;
use JchOptimize\Log\WordpressNoticeLogger;

use function __;
use function wp_redirect;

class RestoreImages extends Controller
{

    public function execute(): bool
    {
        /** @var WordpressNoticeLogger $logger */
        $logger = $this->logger;
        $mResult = Tasks::restoreBackupImages();


        if ($mResult === 'SOMEIMAGESDIDNTRESTORE') {
            $logger->warning(__('Failed restoring all original images', 'jch-optimize'));

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
            $logger->success(__('Successfully restored all images', 'jch-optimize'));

            $result = true;
        }

        wp_redirect('options-general.php?page=jch_optimize&tab=optimizeimages');

        return $result;
    }
}
