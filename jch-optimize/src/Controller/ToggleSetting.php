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

namespace JchOptimize\WordPress\Controller;

use _JchOptimizeVendor\V91\Joomla\Input\Input;
use JchOptimize\Core\Admin\Icons;
use JchOptimize\Core\Mvc\Controller;
use JchOptimize\Core\Platform\CacheInterface;
use JchOptimize\WordPress\Model\Configure;

use function array_column;
use function array_intersect_key;
use function array_map;
use function array_merge;
use function array_values;
use function check_ajax_referer;
use function json_encode;

class ToggleSetting extends Controller
{
    public function __construct(private Configure $model, private CacheInterface $cache, ?Input $input)
    {
        parent::__construct($input);
    }

    public function execute(): bool
    {
        /** @var Input $input */
        $input = $this->getInput();
        /** @var string|null $setting */
        $setting = $input->get('setting');

        if (check_ajax_referer($setting)) {
            $this->model->toggleSetting($setting);
        }

        /** @var string|null $currentSettingValue */
        $currentSettingValue = $this->model->getState()->get($setting);

        if ($setting == 'integrated_page_cache_enable') {
            $currentSettingValue = $this->cache->isPageCacheEnabled($this->model->getState());
        }

        $class = $currentSettingValue ? 'enabled' : 'disabled';
        $class2 = '';
        $auto = false;

        if ($setting == 'pro_reduce_unused_css') {
            $class2 = $this->model->getState()->get('optimizeCssDelivery_enable') ? 'enabled' : 'disabled';
        }

        if ($setting == 'optimizeCssDelivery_enable') {
            $class2 = $this->model->getState()->get('pro_reduce_unused_css') ? 'enabled' : 'disabled';
        }

        if ($setting == 'combine_files_enable' && $currentSettingValue) {
            $auto = $this->getEnabledAutoSetting();
        }

        echo json_encode(['class' => $class, 'class2' => $class2, 'auto' => $auto]);

        return true;
    }

    private function getEnabledAutoSetting(): ?string
    {
        $autoSettingsMap = Icons::autoSettingsArrayMap();

        /** @psalm-suppress TooManyArguments */
        $autoSettingsInitialized = array_map(function () {
            return '0';
        }, $autoSettingsMap);

        $currentAutoSettings = array_intersect_key($this->model->getState()->toArray(), $autoSettingsInitialized);
        //order array
        $orderedCurrentAutoSettings = array_merge($autoSettingsInitialized, $currentAutoSettings);

        $autoSettings = ['minimum', 'intermediate', 'average', 'deluxe', 'premium', 'optimum'];

        for ($j = 0; $j < 6; $j++) {
            if (array_values($orderedCurrentAutoSettings) === array_column($autoSettingsMap, 's' . ($j + 1))) {
                return $autoSettings[$j];
            }
        }

        //No auto setting configured
        return null;
    }
}
