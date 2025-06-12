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

use _JchOptimizeVendor\V91\Joomla\Filesystem\File;
use JchOptimize\Core\Mvc\Controller;
use JchOptimize\WordPress\Model\BulkSettings;

use function basename;
use function check_admin_referer;
use function file_exists;
use function header;
use function nocache_headers;

class ExportSettings extends Controller
{
    public function __construct(private BulkSettings $bulkSettings)
    {
        parent::__construct();
    }

    #[NoReturn] public function execute(): bool
    {
        check_admin_referer('jch_bulksettings');

        $file = $this->bulkSettings->exportSettings();

        if (file_exists($file)) {
            header('Content-Description: FileTransfer');
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            nocache_headers();
            header('Content-Length: ' . filesize($file));
            while (ob_get_level()) {
                ob_end_clean();
            }
            readfile($file);

            File::delete($file);

            die();
        }

        return true;
    }
}
