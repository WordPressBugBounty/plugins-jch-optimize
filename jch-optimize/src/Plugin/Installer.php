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

namespace JchOptimize\WordPress\Plugin;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareTrait;
use _JchOptimizeVendor\V91\Joomla\Filesystem\File;
use _JchOptimizeVendor\V91\Joomla\Filesystem\Folder;
use Exception;
use JchOptimize\Core\Admin\AdminHelper;
use JchOptimize\Core\Admin\AdminTasks;
use JchOptimize\Core\Model\CacheMaintainer;
use JchOptimize\Core\Registry;
use JchOptimize\WordPress\Model\ReCache;

use function defined;
use function delete_option;
use function dirname;
use function file_exists;
use function file_get_contents;
use function is_dir;
use function json_decode;
use function md5_file;
use function update_option;

use const ABSPATH;
use const JCH_PLUGIN_DIR;
use const JCH_PRO;
use const JCH_VERSION;
use const WPMU_PLUGIN_DIR;

class Installer implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    private ?ReCache $recache = null;

    public function __construct(
        private Registry $params,
        private AdminTasks $tasks,
        private AdminHelper $helper,
        private CacheMaintainer $cacheMaintainer,
        Container $container
    ) {
        $this->setContainer($container);

        if (JCH_PRO) {
            $this->recache = $this->getContainer()->get(ReCache::class);
        }
    }

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
        $this->tasks->leverageBrowserCaching();

        $this->installMUPlugin();
        $this->rescheduleRecacheCron();
    }

    public function installMUPlugin(): void
    {
        if (JCH_PRO) {
            // Copy the mu-plugins in the correct folder

            $mu_folder = $this->getMUPluginDir();

            if (!is_dir($mu_folder)) {
                Folder::create($mu_folder);
            }

            $src = JCH_PLUGIN_DIR . 'mu-plugins/jch-optimize-mode-switcher.php';
            $target = $mu_folder . '/jch-optimize-mode-switcher.php';

            $header = <<<PHP
<?php

/**
 * Plugin Name: JCH Optimize Mode Switcher
 * Plugin URI: https://www.jch-optimize.net/
 * Description: Boost your WordPress site's performance with JCH Optimize as measured on PageSpeed
 * Version: {VERSION}
 * Author: Samuel Marshall
 * License: GNU/GPLv3
 */
PHP;

            $buffer = str_replace(['<?php', '{VERSION}'], [$header, JCH_VERSION], file_get_contents($src));
            File::write($target, $buffer);
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

        $this->cacheMaintainer->cleanCache();
        $this->tasks->cleanHtaccess();
        $this->deleteMUPlugin();
        $this->clearRecacheCron();
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
        //Update new Load WEBP setting
        /** @var string|null $loadWebp */
        $loadWebp = $this->params->get('pro_load_webp_images');
        /** @var string|null $nextGenImages */
        $nextGenImages = $this->params->get('pro_next_gen_images');

        if (is_null($loadWebp) && $nextGenImages) {
            $this->params->set('pro_load_webp_images', '1');
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
            $oldJsSettingValue = json_decode(json_encode($this->params->get($oldJsSetting)), true);

            if ($oldJsSettingValue) {
                $firstValue = array_shift($oldJsSettingValue);
                if (!isset($firstValue['url']) && !isset($firstValue['script'])) {
                    $updateJsSettings = true;
                }

                break;
            }
        }

        if ($updateJsSettings) {
            $dontMoveJs = (array)$this->params->get('excludeJs');
            $dontMoveScripts = (array)$this->params->get('dontmoveScripts');
            $this->params->remove('dontmoveJs');
            $this->params->remove('dontmoveScripts');

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
                $excludeJsPeoSetting = (array)$this->params->get($excludeJsPeoSettingName);
                $this->params->remove($excludeJsPeoSettingName);
                $newExcludeJs_peo = [];
                $i = 0;

                foreach ($excludeJsPeoSetting as $excludeJsPeoSettingValue) {
                    $newExcludeJs_peo[$i][$settingsMap['valueType']] = $excludeJsPeoSettingValue;

                    foreach ($settingsMap['dontmove'] as $dontMoveValue) {
                        if (str_contains($excludeJsPeoSettingValue, $dontMoveValue)) {
                            $newExcludeJs_peo[$i]['dontmove'] = 'on';
                        }
                    }
                    $i++;
                }

                /** @var string[] $excludeJsIeoSetting */
                $excludeJsIeoSetting = $this->params->get($settingsMap['ieo']);
                $this->params->remove($settingsMap['ieo']);

                foreach ($excludeJsIeoSetting as $excludeJsIeoSettingValue) {
                    $i++;
                    $newExcludeJs_peo[$i][$settingsMap['valueType']] = $excludeJsIeoSettingValue;
                    $newExcludeJs_peo[$i]['ieo'] = 'on';

                    foreach ($settingsMap['dontmove'] as $dontMoveValue) {
                        if (str_contains($excludeJsIeoSettingValue, $dontMoveValue)) {
                            $newExcludeJs_peo[$i]['dontmove'] = 'on';
                        }
                    }
                }

                $this->params->set($excludeJsPeoSettingName, $newExcludeJs_peo);
            }

            update_option('jch-optimize_settings', $this->params->toArray());
        }
    }

    public function updateMUPlugins(): void
    {
        $mu_folder = $this->getMUPluginDir();

        $installedMU = $mu_folder . '/jch-optimize-mode-switcher.php';


        if (
            file_exists($installedMU)
            && md5_file(
                JCH_PLUGIN_DIR . 'mu-plugins/jch-optimize-mode-switcher.php'
            ) !== md5_file($installedMU)
        ) {
            $this->installMUPlugin();
        }
    }

    public function fixMetaFileSecurity(): void
    {
        $metaFile = $this->helper->getMetaFile();
        $metaFileDir = dirname($metaFile);

        if (
            file_exists($metaFile)
            && (!file_exists($metaFileDir . '/index.html')
                || !file_exists($metaFileDir . '/.htaccess'))
        ) {
            $optimizedFiles = $this->helper->getOptimizedFiles();
            File::delete($metaFile);

            foreach ($optimizedFiles as $files) {
                $this->helper->markOptimized($files);
            }
        }
    }

    private function rescheduleRecacheCron(): void
    {
        $this->recache?->reSchedule();
    }

    private function clearRecacheCron(): void
    {
        $this->recache?->clearSchedule();
    }
}
