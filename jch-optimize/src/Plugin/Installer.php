<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2020 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Plugin;

use Exception;
use JchOptimize\ContainerFactory;
use JchOptimize\Core\Admin\Helper as AdminHelper;
use JchOptimize\Core\Admin\Tasks;
use JchOptimize\Core\Filesystem\File;
use JchOptimize\Core\Filesystem\Folder;
use JchOptimize\Core\Registry;
use JchOptimize\Model\Cache;

use function defined;
use function delete_option;
use function dirname;
use function file_exists;
use function is_dir;
use function json_decode;
use function md5_file;
use function update_option;

use const ABSPATH;
use const JCH_PLUGIN_DIR;
use const JCH_PRO;
use const WPMU_PLUGIN_DIR;

class Installer
{
    /**
     * Fires when plugin is activated and create a dir.php file in plugin root containing
     * absolute path of plugin install
     */
    public function activate(): void
    {
        $file = JCH_PLUGIN_DIR . 'dir.php';
        $absPath = ABSPATH;
        $code = <<<PHPCODE
<?php
           
\$DIR = '$absPath';
           
PHPCODE;

        File::write($file, $code);
        Tasks::leverageBrowserCaching();

        $this->installMUPlugin();
    }

    public function installMUPlugin(): void
    {
        if (JCH_PRO) {
            // Copy the mu-plugins in the correct folder

            $mu_folder = $this->getMUPluginDir();

            if (!is_dir($mu_folder)) {
                Folder::create($mu_folder);
            }

            File::copy(
                JCH_PLUGIN_DIR . 'mu-plugins/jch-optimize-mode-switcher.php',
                $mu_folder . '/jch-optimize-mode-switcher.php'
            );
        }
    }

    private function getMUPluginDir(): string
    {
        $mu_folder = ABSPATH . 'wp-content/mu-plugins';
        if (defined('WPMU_PLUGIN_DIR') && WPMU_PLUGIN_DIR) {
            $mu_folder = WPMU_PLUGIN_DIR;
        }

        return $mu_folder;
    }

    public function deactivate(): void
    {
        delete_option('jch-optimize_settings');

        $container = ContainerFactory::getContainer();
        /** @var Cache $cache */
        $cache = $container->get(Cache::class);
        $cache->cleanCache();

        Tasks::cleanHtaccess();

        $this->deleteMUPlugin();
    }

    public function deleteMUPlugin(): void
    {
        if (JCH_PRO) {
            $mu_folder = $this->getMUPluginDir();

            if (defined('WPMU_PLUGIN_DIR') && WPMU_PLUGIN_DIR) {
                $mu_folder = WPMU_PLUGIN_DIR;
            }

            try {
                File::delete($mu_folder . '/jch-optimize-mode-switcher.php');
            } catch (Exception $e) {
            }
        }
    }

    public function updateSettings(): void
    {
        $container = ContainerFactory::getContainer();
        /** @var Registry $params */
        $params = $container->get('params');

        //Update new Load WEBP setting
        /** @var string|null $loadWebp */
        $loadWebp = $params->get('pro_load_webp_images');
        /** @var string|null $nextGenImages */
        $nextGenImages = $params->get('pro_next_gen_images');

        if (is_null($loadWebp) && $nextGenImages) {
            $params->set('pro_load_webp_images', '1');
        }

        //Update Exclude JavaScript settings
        $oldJsSettings = [
            'excludeJs_peo',
            'excludeJsComponents_peo',
            'excludeScripts_peo',
            'excludeJs',
            'excludeJsComponents',
            'excludeScripts',
            'dontmoveJs',
            'dontmoveScripts',
        ];

        $updateJsSettings = false;

        foreach ($oldJsSettings as $oldJsSetting) {
            /** @var array $oldJsSettingValue */
            $oldJsSettingValue = json_decode(json_encode($params->get($oldJsSetting)), true);

            if ($oldJsSettingValue) {
                if (!isset($oldJsSettingValue[0]['url']) && !isset($oldJsSettingValue[0]['script'])) {
                    $updateJsSettings = true;
                }

                break;
            }
        }

        if ($updateJsSettings) {
            $dontMoveJs = (array)$params->get('excludeJs');
            $dontMoveScripts = (array)$params->get('dontmoveScripts');
            $params->remove('dontmoveJs');
            $params->remove('dontmoveScripts');

            /** @var array<string, array{ieo:string, valueType: string, dontmove: array<array-key, string>}> $excludeJsPeoSettingsMap */
            $excludeJsPeoSettingsMap = [
                'excludeJs_peo'           => [
                    'ieo'       => 'excludeJs',
                    'valueType' => 'url',
                    'dontmove'  => $dontMoveJs
                ],
                'excludeJsComponents_peo' => [
                    'ieo'       => 'excludeJsComponents',
                    'valueType' => 'url',
                    'dontmove'  => $dontMoveJs
                ],
                'excludeScripts_peo'      => [
                    'ieo'       => 'excludeScripts',
                    'valueType' => 'script',
                    'dontmove'  => $dontMoveScripts
                ]
            ];

            foreach ($excludeJsPeoSettingsMap as $excludeJsPeoSettingName => $settingsMap) {
                /** @var string[] $excludeJsPeoSetting */
                $excludeJsPeoSetting = (array)$params->get($excludeJsPeoSettingName);
                $params->remove($excludeJsPeoSettingName);
                $newExcludeJs_peo = [];
                $i = 0;

                foreach ($excludeJsPeoSetting as $excludeJsPeoSettingValue) {
                    $newExcludeJs_peo[$i][$settingsMap['valueType']] = $excludeJsPeoSettingValue;

                    foreach ($settingsMap['dontmove'] as $dontMoveValue) {
                        if (strpos($excludeJsPeoSettingValue, $dontMoveValue) !== false) {
                            $newExcludeJs_peo[$i]['dontmove'] = 'on';
                        }
                    }
                    $i++;
                }

                /** @var string[] $excludeJsIeoSetting */
                $excludeJsIeoSetting = $params->get($settingsMap['ieo']);
                $params->remove($settingsMap['ieo']);

                foreach ($excludeJsIeoSetting as $excludeJsIeoSettingValue) {
                    $i++;
                    $newExcludeJs_peo[$i][$settingsMap['valueType']] = $excludeJsIeoSettingValue;
                    $newExcludeJs_peo[$i]['ieo'] = 'on';

                    foreach ($settingsMap['dontmove'] as $dontMoveValue) {
                        if (strpos($excludeJsIeoSettingValue, $dontMoveValue) !== false) {
                            $newExcludeJs_peo[$i]['dontmove'] = 'on';
                        }
                    }
                }

                $params->set($excludeJsPeoSettingName, $newExcludeJs_peo);
            }

            update_option('jch-optimize_settings', $params->toArray());
        }
    }

    public function updateMUPlugins(): void
    {
        $mu_folder = $this->getMUPluginDir();

        $installedMU = $mu_folder . '/jch-optimize-mode-switcher.php';


        if (file_exists($installedMU) && md5_file(
            JCH_PLUGIN_DIR . 'mu-plugins/jch-optimize-mode-switcher.php'
        ) !== md5_file($installedMU)) {
            $this->installMUPlugin();
        }
    }

    public function fixMetaFileSecurity()
    {
        $metaFile = AdminHelper::getMetaFile();
        $metaFileDir = dirname($metaFile);

        if (file_exists($metaFile)
            && (!file_exists($metaFileDir . '/index.html')
                || !file_exists($metaFileDir . '/.htaccess'))
        ) {
            /** @var string[] $optimizedFiles */
            $optimizedFiles = AdminHelper::getOptimizedFiles();
            File::delete($metaFile);

            foreach ($optimizedFiles as $files) {
                AdminHelper::markOptimized($files);
            }
        }
    }
}
