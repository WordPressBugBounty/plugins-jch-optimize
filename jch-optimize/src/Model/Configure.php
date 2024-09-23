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

namespace JchOptimize\Model;

use JchOptimize\Core\Admin\Ajax\Ajax;
use JchOptimize\Core\Admin\Icons;
use JchOptimize\Core\Mvc\Model;
use JchOptimize\Core\Admin\Json;
use JchOptimize\Core\PageCache\CaptureCache;
use JchOptimize\Platform\Cache;

use function abs;
use function array_column;
use function array_combine;
use function array_keys;
use function array_merge;
use function in_array;
use function is_null;
use function json_encode;
use function rawurlencode;

class Configure extends Model
{
    /**
     * @param $autoSetting
     *
     * @return bool
     */
    public function applyAutoSetting(string $autoSetting): bool
    {
        $autoSettingsMap = Icons::autoSettingsArrayMap();

        //$autoSetting = $this->state->get( 'autosetting', 's1' );

        if (! in_array($autoSetting, ['s1', 's2', 's3', 's4', 's5', 's6'])) {
            return false;
        }

        //Get array of settings that correspond to the selected auto setting
        $settingsArray = array_column($autoSettingsMap, $autoSetting);

        //Map settings array to name of settings
        $indexedSettingsArray = array_combine(array_keys($autoSettingsMap), $settingsArray);

        //Save each setting to state
        foreach ($indexedSettingsArray as $name => $value) {
            $this->state->set($name, $value);
        }

        $this->state->set('combine_files_enable', '1');

        //Save state to plugin options in database
        return update_option('jch-optimize_settings', $this->state->toArray());
    }

    /**
     * @param string|null $setting
     *
     * @return bool
     */
    public function toggleSetting(?string $setting): bool
    {
        if (is_null($setting)) {
            return false;
        }

        //Get the currently saved setting from state
        $currentSetting = (int)$this->state->get($setting);
        //Calculate the inverse to 'toggle'
        $newSetting = (string)abs($currentSetting - 1);

        //Reduce unused CSS only works if Optimize CSS Delivery is enabled
        if ($setting == 'pro_reduce_unused_css' && $newSetting == '1') {
            $this->state->set('optimizeCssDelivery_enable', '1');
        }

        if ($setting == 'optimizeCssDelivery_enable' && $newSetting == '0') {
            $this->state->set('pro_reduce_unused_css', '0');
        }

        if ($setting == 'integrated_page_cache_enable') {
            $bCurrentSetting = Cache::isPageCacheEnabled($this->state);
            $newSetting      = (string)(! $bCurrentSetting);

            $this->state->set('cache_enable', $newSetting);

            if (JCH_PRO) {
                /** @see CaptureCache::updateHtaccess() */
                $this->getContainer()->get(CaptureCache::class)->updateHtaccess();
            }
        }

        //Update new setting in model state
        $this->state->set($setting, $newSetting);

        //save state to plugin options in database
        return update_option('jch-optimize_settings', $this->state->toArray());
    }
}
