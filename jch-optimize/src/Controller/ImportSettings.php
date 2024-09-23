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

use Exception;
use JchOptimize\Core\Input;
use JchOptimize\Core\Mvc\Controller;
use JchOptimize\Core\Uri\UploadedFile;
use JchOptimize\Log\WordpressNoticeLogger;
use JchOptimize\Model\BulkSettings;

use function __;
use function check_admin_referer;
use function sprintf;
use function wp_redirect;

use const UPLOAD_ERR_CANT_WRITE;
use const UPLOAD_ERR_EXTENSION;
use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_NO_TMP_DIR;
use const UPLOAD_ERR_OK;
use const UPLOAD_ERR_PARTIAL;

class ImportSettings extends Controller
{
    private BulkSettings $bulkSettings;

    public function __construct(BulkSettings $bulkSettings, ?Input $input)
    {
        $this->bulkSettings = $bulkSettings;

        parent::__construct($input);
    }


    public function execute(): bool
    {
        check_admin_referer('jch_bulksettings');

        /** @var Input $input */
        $input = $this->getInput();
        /** @var WordpressNoticeLogger $logger */
        $logger = $this->logger;
        /** @var array{tmp_name:string, size:int, error:int, name?:string, type?:string}|null $file */
        $file = $input->files->get('file', []);

        if (empty($file)) {
            $logger->error(__('No file was uploaded'));

            $this->redirect();

            return false;
        }

        $uploadErrorMap = [
            UPLOAD_ERR_OK => __('File uploaded successfully'),
            UPLOAD_ERR_INI_SIZE => __('File exceeded the limit set in php.ini'),
            UPLOAD_ERR_FORM_SIZE => __('File exceeded the value set in form'),
            UPLOAD_ERR_PARTIAL => __(' File was only partially uploaded'),
            UPLOAD_ERR_NO_FILE => __('No file was uploaded'),
            UPLOAD_ERR_NO_TMP_DIR => __('No tmp directory configured for file upload'),
            UPLOAD_ERR_CANT_WRITE => __('Upload directory not writable'),
            UPLOAD_ERR_EXTENSION => __('An unknown extension prevented the file from being loaded')
        ];

        try {
            $uploadedFile = new UploadedFile(
                $file['tmp_name'],
                $file['size'],
                $file['error'],
                $file['name'] ?? null,
                $file['type'] ?? null
            );

            if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
                throw new Exception($uploadErrorMap[$uploadedFile->getError()]);
            }
        } catch (Exception $exception) {
            $logger->error(sprintf(__('Error uploaded file: %s'), $exception->getMessage()));

            $this->redirect();

            return false;
        }

        try {
            $this->bulkSettings->importSettings($uploadedFile);
        } catch (Exception $exception) {
            $logger->error(sprintf(__('Error importing settings: %s'), $exception->getMessage()));

            return false;
        }

        $logger->success(__('Settings successfully imported'));

        $this->redirect();

        return true;
    }

    private function redirect(): void
    {
        wp_redirect('options-general.php?page=jch_optimize');
    }
}
