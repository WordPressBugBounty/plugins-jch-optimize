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

namespace JchOptimize\WordPress\Model;

use _JchOptimizeVendor\V91\Joomla\Filesystem\File;
use _JchOptimizeVendor\V91\Joomla\Filesystem\Folder;
use _JchOptimizeVendor\V91\Psr\Http\Message\UploadedFileInterface;
use JchOptimize\Core\Mvc\Model;
use JchOptimize\Core\Registry;
use JchOptimize\Core\SystemUri;

use function array_unique;
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

    public function importSettings(UploadedFileInterface $uploadedFile): void
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

        $uploadedSettings = (new Registry())->loadFile($targetPath);

        File::delete($targetPath);

        if ($uploadedSettings->get('merge')) {
            $this->mergeSettings($uploadedSettings);
        } else {
            $this->setState($uploadedSettings);
            update_option('jch-optimize_settings', $uploadedSettings->toArray());
        }
    }

    private function mergeSettings(Registry $uploadedSettings): void
    {
        $uploadedSettings->remove('merge');

        foreach ($uploadedSettings as $setting => $value) {
            if (is_array($value)) {
                $mergedSetting = array_unique(array_merge($this->state->get($setting, []), $value));
            } else {
                $mergedSetting = $value;
            }

            $this->state->set($setting, $mergedSetting);
        }

        update_option('jch-optimize_settings', $this->state->toArray());
    }
}
