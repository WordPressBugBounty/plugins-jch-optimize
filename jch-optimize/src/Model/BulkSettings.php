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

namespace JchOptimize\Model;

use JchOptimize\Core\Filesystem\File;
use JchOptimize\Core\Filesystem\Folder;
use JchOptimize\Core\Mvc\Model;
use JchOptimize\Core\Registry;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\UploadedFile;

use function dirname;
use function file_exists;
use function is_dir;
use function update_option;

use const JCH_PLUGIN_DIR;

class BulkSettings extends Model
{
    public function setDefaultSettings(): bool
    {
        return update_option('jch-optimize_settings', []);
    }

    public function exportSettings(): string
    {
        $file = JCH_PLUGIN_DIR . 'tmp/' . SystemUri::currentUri()->getHost() . '_jchoptimize_settings.json';

        $params = $this->state->toString();

        File::write($file, $params);

        return $file;
    }

    public function importSettings(UploadedFile $uploadedFile): void
    {
        $targetPath = JCH_PLUGIN_DIR . 'tmp/' . $uploadedFile->getClientFilename();

        //If file not already at target path, move it
        if (!file_exists($targetPath)) {
            //Let's ensure that the tmp directory is there
            if (!is_dir(dirname($targetPath))) {
                Folder::create(dirname($targetPath));
            }

            $uploadedFile->moveTo($targetPath);
        }

        $params = (new Registry())->loadFile($targetPath);

        File::delete($targetPath);

        $this->setState($params);

        update_option('jch-optimize_settings', $params->toArray());
    }
}
