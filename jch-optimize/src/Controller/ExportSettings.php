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

namespace JchOptimize\Controller;

use JchOptimize\Core\Filesystem\File;
use JchOptimize\Core\Mvc\Controller;
use JchOptimize\Model\BulkSettings;

use function basename;
use function check_admin_referer;
use function file_exists;
use function flush;
use function header;
use function nocache_headers;
use function ob_clean;

class ExportSettings extends Controller
{
    private BulkSettings $bulkSettings;

    public function __construct(BulkSettings $bulkSettings)
    {
        $this->bulkSettings = $bulkSettings;

        parent::__construct();
    }

    public function execute(): bool
    {
        check_admin_referer('jch_bulksettings');

        $file = $this->bulkSettings->exportSettings();

        if (file_exists($file)) {
            header('Content-Description: FileTransfer');
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            nocache_headers();
            header('Content-Length: ' . filesize($file));
            ob_clean();
            flush();
            readfile($file);

            File::delete($file);
        }

        return true;
    }
}
