<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Admin;

use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareTrait;
use CodeAlfa\Component\JchOptimize\Administrator\Extension\JchOptimizeComponent;
use CodeAlfa\Component\JchOptimize\Administrator\Model\ModeSwitcherModel;
use Exception;
use JchOptimize\Core\Platform\CacheInterface;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Platform\UtilityInterface;
use JchOptimize\Core\Registry;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

use function array_column;
use function array_combine;
use function array_flip;
use function array_intersect_key;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function defined;
use function htmlspecialchars;
use function str_replace;
use function strtolower;
use function trim;

use const JCH_PLATFORM;

defined('_JCH_EXEC') or die('Restricted access');

class Icons implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function __construct(
        private Registry $params,
        private CacheInterface $cache,
        private PathsInterface $paths,
        private UtilityInterface $utility
    ) {
    }

    public static function getAutoSettingsArray(): array
    {
        return [
            [
                'name' => 'Minimum',
                'icon' => 'fa fa-compress-alt',
                'setting' => 1,
            ],
            [
                'name' => 'Intermediate',
                'icon' => 'fa fa-align-center',
                'setting' => 2
            ],
            [
                'name' => 'Average',
                'icon' => 'fa fa-chart-area',
                'setting' => 3
            ],
            [
                'name' => 'Deluxe',
                'icon' => 'fa fa-step-forward',
                'setting' => 4
            ],
            [
                'name' => 'Premium',
                'icon' => 'fa fa-fast-forward',
                'setting' => 5
            ],
            [
                'name' => 'Optimum',
                'icon' => 'fa fa-tachometer-alt fa-fw',
                'setting' => 6
            ]
        ];
    }

    /**
     * @param $buttons
     *
     * @return string
     */
    public function printIconsHTML($buttons): string
    {
        $sIconsHTML = '';

        foreach ($buttons as $button) {
            $sContentAttr = $this->utility->bsTooltipContentAttribute();
            $sTooltip = @$button['tooltip']
                ? " class=\"hasPopover fig-caption\" title=\"{$button['name']}\""
                    . " {$sContentAttr}=\"{$button['tooltip']}\" "
                : ' class="fig-caption"';
            $sIconSrc = $this->paths->iconsUrl() . '/' . $button['icon'];
            $sToggle = '<span class="toggle-wrapper" ><i class="toggle fa"></i></span>';
            $onClickFalse = '';

            if (!JCH_PRO && !empty($button['proonly'])) {
                $button['link'] = '';
                $button['script'] = '';
                $button['class'] = 'disabled proonly';
                $sToggle = '<span id="proonly-span"><em>(Pro Only)</em></span>';
                $onClickFalse = ' onclick="return false;"';
            }

            $sIconsHTML .= <<<HTML
<figure id="{$button['id']}" class="icon {$button['class']}">
	<a href="{$button['link']}" class="btn" {$button['script']}{$onClickFalse}>
		<img src="{$sIconSrc}" alt="" width="50" height="50" />
		<span{$sTooltip}>{$button['name']}</span>
		{$sToggle}
	</a>
</figure>

HTML;
        }

        return $sIconsHTML;
    }

    public function compileAutoSettingsIcons($settings): array
    {
        $buttons = [];

        for ($i = 0; $i < count($settings); $i++) {
            $id = $this->generateIdFromName($settings[$i]['name']);
            $buttons[$i]['link'] = '';
            $buttons[$i]['icon'] = $settings[$i]['icon'];
            $buttons[$i]['name'] = $settings[$i]['name'];
            $buttons[$i]['script'] = "onclick=\"jchPlatform.applyAutoSettings('{$settings[$i]['setting']}', '{$id}', '"
                . $this->utility->getNonce(
                    's' . $settings[$i]['setting']
                ) . "'); return false;\"";
            $buttons[$i]['id'] = $id;
            $buttons[$i]['class'] = ['auto-setting', 'disabled'];
            $buttons[$i]['tooltip'] = htmlspecialchars(self::generateAutoSettingTooltip($settings[$i]['setting']));
            $buttons[$i]['enabled'] = false;
        }

        $sCombineFilesEnable = $this->params->get('combine_files_enable', '0');
        $aParamsArray = $this->params->toArray();

        $aAutoSettings = self::autoSettingsArrayMap();

        $aAutoSettingsInit = array_map(function ($a) {
            return '0';
        }, $aAutoSettings);

        $aCurrentAutoSettings = array_intersect_key($aParamsArray, $aAutoSettingsInit);
        //order array
        $aCurrentAutoSettings = array_merge($aAutoSettingsInit, $aCurrentAutoSettings);

        if ($sCombineFilesEnable) {
            for ($j = 0; $j < 6; $j++) {
                if (array_values($aCurrentAutoSettings) === array_column($aAutoSettings, 's' . ($j + 1))) {
                    $buttons[$j]['class'] = ['auto-setting', 'enabled'];
                    $buttons[$j]['enabled'] = true;

                    break;
                }
            }
        }

        return $buttons;
    }

    /**
     * @param $name
     *
     * @return string
     */
    private function generateIdFromName($name): string
    {
        return strtolower(str_replace([' ', '/'], ['-', ''], trim($name)));
    }

    private function generateAutoSettingTooltip($setting): string
    {
        $aAutoSettingsMap = self::autoSettingsArrayMap();
        $aCurrentSettingValues = array_column($aAutoSettingsMap, 's' . $setting);
        $aCurrentSettingArray = array_combine(array_keys($aAutoSettingsMap), $aCurrentSettingValues);
        $aSetting = array_map(function ($v) {
            return ($v == '1') ? 'on' : 'off';
        }, $aCurrentSettingArray);

        return <<<HTML
<h4 class="list-header">CSS</h4>
<ul class="unstyled list-unstyled">
<li>Optimize CSS <i class="toggle fa fa-toggle-{$aSetting['css']}"></i></li>
<li>Minify CSS <i class="toggle fa fa-toggle-{$aSetting['css_minify']}"></i></li>
<li>Resolve @imports <i class="toggle fa fa-toggle-{$aSetting['replaceImports']}"></i></li>
<li>Include in-page styles <i class="toggle fa fa-toggle-{$aSetting['inlineStyle']}"></i></li>
</ul>
<h4 class="list-header">JavaScript</h4>
<ul class="unstyled list-unstyled">
<li>Optimize JavaScript <i class="toggle fa fa-toggle-{$aSetting['javascript']}"></i></li>
<li>Minify JavaScript <i class="toggle fa fa-toggle-{$aSetting['js_minify']}"></i></li>
<li>Include in-page scripts <i class="toggle fa fa-toggle-{$aSetting['inlineScripts']}"></i></li>
<li>Place JavaScript at bottom <i class="toggle fa fa-toggle-{$aSetting['bottom_js']}"></i></li>
<li>Defer JavaScript <i class="toggle fa fa-toggle-{$aSetting['loadAsynchronous']}"></i></li>
</ul>
<h4 class="list-header">Optimize files</h4>
<ul class="unstyled list-unstyled">
<li>Gzip JavaScript/CSS <i class="toggle fa fa-toggle-{$aSetting['gzip']}"></i> </li>
<li>Minify HTML <i class="toggle fa fa-toggle-{$aSetting['html_minify']}"></i> </li>
<li>Include third-party files <i class="toggle fa fa-toggle-{$aSetting['includeAllExtensions']}"></i></li>
<li>Include external files <i class="toggle fa fa-toggle-{$aSetting['phpAndExternal']}"></i></li>
</ul>
HTML;
    }

    public static function autoSettingsArrayMap(): array
    {
        return [
            'css' => [
                's1' => '1',
                's2' => '1',
                's3' => '1',
                's4' => '1',
                's5' => '1',
                's6' => '1'
            ],
            'javascript' => [
                's1' => '1',
                's2' => '1',
                's3' => '1',
                's4' => '1',
                's5' => '1',
                's6' => '1'
            ],
            'gzip' => [
                's1' => '0',
                's2' => '1',
                's3' => '1',
                's4' => '1',
                's5' => '1',
                's6' => '1'
            ],
            'css_minify' => [
                's1' => '0',
                's2' => '1',
                's3' => '1',
                's4' => '1',
                's5' => '1',
                's6' => '1'
            ],
            'js_minify' => [
                's1' => '0',
                's2' => '1',
                's3' => '1',
                's4' => '1',
                's5' => '1',
                's6' => '1'
            ],
            'html_minify' => [
                's1' => '0',
                's2' => '1',
                's3' => '1',
                's4' => '1',
                's5' => '1',
                's6' => '1'
            ],
            'includeAllExtensions' => [
                's1' => '0',
                's2' => '0',
                's3' => '1',
                's4' => '1',
                's5' => '1',
                's6' => '1'
            ],
            'replaceImports' => [
                's1' => '0',
                's2' => '0',
                's3' => '0',
                's4' => '1',
                's5' => '1',
                's6' => '1'
            ],
            'phpAndExternal' => [
                's1' => '0',
                's2' => '0',
                's3' => '0',
                's4' => '1',
                's5' => '1',
                's6' => '1'
            ],
            'inlineStyle' => [
                's1' => '0',
                's2' => '0',
                's3' => '0',
                's4' => '1',
                's5' => '1',
                's6' => '1'
            ],
            'inlineScripts' => [
                's1' => '0',
                's2' => '0',
                's3' => '0',
                's4' => '1',
                's5' => '1',
                's6' => '1'
            ],
            'bottom_js' => [
                's1' => '0',
                's2' => '0',
                's3' => '0',
                's4' => '0',
                's5' => '1',
                's6' => '1'
            ],
            'loadAsynchronous' => ['s1' => '0', 's2' => '0', 's3' => '0', 's4' => '0', 's5' => '0', 's6' => '1']
        ];
    }

    public function getApi2UtilityArray(): array
    {
        return self::getUtilityArray(['restoreimages', 'deletebackups']);
    }

    /**
     * @param string[] $actions
     *
     * @psalm-param list{0?: 'restoreimages', 1?: 'deletebackups'} $actions
     */
    public function getUtilityArray(array $actions = []): array
    {
        $aUtilities = [
            ($action = 'browsercaching') => [
                'action' => $action,
                'icon' => 'fa fa-file-alt',
                'name' => 'Optimize .htaccess',
                'tooltip' => $this->utility->translate(
                    'Use this button to add codes to your htaccess file to enable leverage browser'
                    . ' caching and gzip compression.'
                )
            ],
            ($action = 'filepermissions') => [
                'action' => $action,
                'icon' => 'file_permissions.png',
                'name' => 'Fix file permissions',
                'tooltip' => $this->utility->translate(
                    "If your site has lost CSS formatting after enabling the plugin,' 
                    . ' the problem could be that the plugin files were installed with incorrect file permissions' 
                    . ' so the browser cannot access the cached combined file.' 
                    . ' Click here to correct the plugin's file permissions."
                )
            ],
            ($action = 'cleancache') => [
                'action' => $action,
                'icon' => 'fa fa-trash-alt',
                'name' => 'Clean Cache',
                'tooltip' => $this->utility->translate(
                    "Click this button to clean the plugin's cache and page cache.' 
                    . ' If you have edited any CSS or JavaScript files you need to clean the cache' 
                    . ' so the changes can be visible."
                ),
                'details' => '<span class="fileSize-container"><span class="fa fa-spinner"></span></span>
                <script>window.addEventListener(\'DOMContentLoaded\', () => {jchPlatform.getCacheInfo();});</script>'
            ],
            ($action = 'orderplugins') => [
                'action' => $action,
                'icon' => 'fa fa-sort',
                'name' => 'Order Plugin',
                'tooltip' => $this->utility->translate(
                    'The published order of the plugin is important!'
                    . ' When you click on this icon, it will attempt to order the plugin correctly.'
                )
            ],
            ($action = 'keycache') => [
                'action' => $action,
                'icon' => 'fa fa-key',
                'name' => 'Generate new cache key',
                'tooltip' => $this->utility->translate(
                    "If you've made any changes to your files generate a new cache' 
                    . 'key to counter browser caching of the old content."
                )
            ],
            ($action = 'recache') => [
                'action' => $action,
                'icon' => 'fa fa-redo',
                'name' => 'Recache',
                'proonly' => true,
                'tooltip' => $this->utility->translate(
                    "Rebuild the cache for all the pages of the site."
                )
            ],
            ($action = 'bulksettings') => [
                'action' => $action,
                'icon' => 'fa fa-sitemap',
                'name' => 'Bulk Settings',
                'tooltip' => $this->utility->translate(
                    "Opens a modal that provides options to import/export settings, or restore to default values."
                ),
                'script' => 'onclick="loadBulkSettingsModal(); return false;"'
            ],
            ($action = 'restoreimages') => [
                'action' => $action,
                'icon' => 'fa fa-trash-restore',
                'name' => 'Restore Original Images',
                'tooltip' => $this->utility->translate(
                    "If you're not satisfied with the images that were optimized you can restore the original' 
                    . 'ones by clicking this button if they were not deleted.' 
                    . 'This will also remove any webp image created from the restored file."
                ),
                'proonly' => true,
            ],
            ($action = 'deletebackups') => [
                'action' => $action,
                'icon' => 'fa fa-trash',
                'name' => 'Delete Backup Images',
                'tooltip' => $this->utility->translate(
                    "This will permanently delete the images that were backed up.' 
                    . 'There's no way to undo this so be sure you're satisfied with the ones that were optimized' 
                    . ' before clicking this button."
                ),
                'proonly' => true,
                'script' => 'onclick="return confirm(\'Are you sure? This cannot be undone!\');"'
            ]

        ];

        if (empty($actions)) {
            return $aUtilities;
        } else {
            return array_intersect_key($aUtilities, array_flip($actions));
        }
    }

    public function compileUtilityIcons($utilityIcons): array
    {
        $icons = [];
        $i = 0;

        foreach ($utilityIcons as $utilityIcon) {
            $icons[$i]['link'] = $this->paths->adminController($utilityIcon['action']);
            $icons[$i]['icon'] = $utilityIcon['icon'];
            $icons[$i]['name'] = $this->utility->translate($utilityIcon['name']);
            $icons[$i]['id'] = $this->generateIdFromName($utilityIcon['name']);
            $icons[$i]['tooltip'] = @$utilityIcon['tooltip'] ?: false;
            $icons[$i]['script'] = @$utilityIcon['script'] ?: '';
            $icons[$i]['proonly'] = @$utilityIcon['proonly'] ?: false;
            $icons[$i]['details'] = @$utilityIcon['details'] ?: '';
            $icons[$i]['class'] = [];

            $i++;
        }

        return $icons;
    }

    public function getToggleSettings($context = ''): array
    {
        $pageCacheTooltip = '';

        if (JCH_PLATFORM == 'Joomla!') {
            $pageCacheTooltip = '<strong>[';

            try {
                $component = Factory::getApplication()->bootComponent('com_jchoptimize');
                if ($component instanceof JchOptimizeComponent) {
                    $modeSwitcher = $component->getMVCFactory()->createModel('ModeSwitcher', 'Administrator');
                    if ($modeSwitcher instanceof ModeSwitcherModel) {
                        $integratedPageCache = $modeSwitcher->getIntegratedPageCachePlugin();
                        if ($integratedPageCache == 'jchoptimizepagecache') {
                            $integratedPageCache = 'jchpagecache';
                        }
                        $pageCacheTooltip .= Text::_($modeSwitcher->pageCachePlugins[$integratedPageCache]);
                        $pageCacheTooltip .= ']</strong><br><br>';
                    }
                }
            } catch (Exception $e) {
            }
        }

        $pageCacheTooltip .= $this->utility->translate('Toggles on/off the Page Cache feature.');

        $features = [
            [
                'name' => 'Add Image Attributes',
                'setting' => ($setting = 'img_attributes_enable'),
                'icon' => 'fa fa-file-code',
                'enabled' => $this->params->get($setting, '0'),
                'tooltip' => $this->utility->translate(
                    'Adds \'height\' and/or \'width\' attributes to &lt:img&gt;\'s, if missing, to reduce CLS.'
                )
            ],
            [
                'name' => 'Lazy Load Images',
                'setting' => ($setting = 'lazyload_enable'),
                'icon' => 'fa fa-images',
                'enabled' => $this->params->get($setting, '0'),
                'tooltip' => $this->utility->translate('Defer images that fall below the fold.')
            ],
            [
                'name'    => 'Load Webp',
                'setting' => ($setting = 'pro_load_webp_images'),
                'icon' => 'fa fa-file-image',
                'enabled' => $this->params->get($setting, '0'),
                'proonly' => true,
                'tooltip' => $this->utility->translate(
                    'Loads generated WEBP images in place of the original ones.'
                    . 'These images must be generated on the Optimize Image tab first.'
                )
            ],
            [
                'name' => 'Load Responsive',
                'setting' => ($setting = 'pro_load_responsive_images'),
                'icon' => 'fa fa-mobile',
                'enabled' => $this->params->get($setting, '0'),
                'proonly' => true,
                'tooltip' => $this->utility->translate(
                    'Use responsive images where available.'
                    . 'These images must be generated on the Optimize Image tab first.'
                )
            ],
            [
                'name' => 'LCP Images',
                'setting' => ($setting = 'pro_lcp_images_enable'),
                'icon' => 'fa fa-portrait',
                'enabled' => $this->params->get($setting, '0'),
                'proonly' => true,
                'tooltip' => $this->utility->translate(
                    'Preload LCP images with a high fetch priority.'
                    . 'These images must be added on the Options page to be discovered.'
                )
            ],
            [
                'name' => 'CDN',
                'setting' => ($setting = 'cookielessdomain_enable'),
                'icon' => 'fa fa-network-wired',
                'enabled' => $this->params->get($setting, '0'),
                'tooltip' => $this->utility->translate(
                    'Loads static assets from a CDN server.'
                    . 'Requires the CDN domain(s) to be configured on the Configuration tab.'
                )
            ],
            [
                'name' => 'Sprite Generator',
                'setting' => ($setting = 'csg_enable'),
                'icon' => 'fa fa-object-group',
                'enabled' => $this->params->get($setting, '0'),
                'tooltip' => $this->utility->translate('Combines select background images into a sprite.')
            ],
            [
                'name' => 'Optimize CSS Delivery',
                'setting' => ($setting = 'optimizeCssDelivery_enable'),
                'icon' => 'fa fa-css3',
                'enabled' => $this->params->get($setting, '0'),
                'tooltip' => $this->utility->translate('Eliminates CSS render-blocking')
            ],
            [
                'name' => 'Http/2 Push',
                'setting' => ($setting = 'http2_push_enable'),
                'icon' => 'fa fa-cloud-upload-alt',
                'enabled' => $this->params->get($setting, '0'),
                'tooltip' => $this->utility->translate(
                    'Preloads critical assets using the http/2 protocol to improve LCP.'
                )
            ],
            [
                'name' => 'Optimize Fonts',
                'setting' => ($setting = 'pro_optimizeFonts_enable'),
                'icon' => 'fa fa-fonticons',
                'enabled' => $this->params->get($setting, '0'),
                'proonly' => true,
                'tooltip' => $this->utility->translate('Optimizes the loading of fonts, including Google Fonts.')
            ],
            [
                'name' => 'Preconnects',
                'setting' => ($setting = 'pro_preconnect_domains_enable'),
                'icon' => 'fa fa-wifi',
                'enabled' => $this->params->get($setting, '0'),
                'proonly' => true,
                'tooltip' => $this->utility->translate(
                    'Preconnect external origins to reduce the impact of third-party domains.'
                )
            ],
            [
                'name' => 'Custom CSS',
                'setting' => ($setting = 'custom_css_enable'),
                'icon' => 'fa fa-code',
                'enabled' => $this->params->get($setting, '0'),
                'tooltip' => $this->utility->translate('Add custom CSS on the Options page.')
            ],
            [
                'name' => 'Page Cache',
                'setting' => 'integrated_page_cache_enable',
                'icon' => 'fa fa-archive',
                'enabled' => $this->cache->isPageCacheEnabled($this->params),
                'tooltip' => $pageCacheTooltip
            ]
        ];

        if ($context == 'images') {
            return array_slice($features, 0, 6);
        } elseif ($context == 'css') {
            return array_slice($features, 6, 6);
        } else {
            return $features;
        }
    }

    public function getCombineFilesEnableSetting(): array
    {
        $nonce = $this->utility->getNonce('combine_files_enable');
        return [
            [
                'name' => 'Combine Files Enable',
                'setting' => ($setting = 'combine_files_enable'),
                'icon' => 'combine_files_enable.png',
                'enabled' => $this->params->get($setting, '1'),
                'script' => "onclick=\"jchPlatform.toggleCombineFilesEnable('$nonce'); return false;\""
            ]
        ];
    }

    public function getAdvancedToggleSettings(): array
    {
        return [
            [
                'name' => 'Reduce Unused CSS',
                'setting' => ($setting = 'pro_reduce_unused_css'),
                'icon' => 'fa fa-file-archive',
                'enabled' => $this->params->get($setting, '0'),
                'proonly' => true,
                'class' => 'warning',
                'tooltip' => $this->utility->translate(
                    'Loads only the critical CSS required for rendering the page above the fold until user'
                    . ' interacts with the page. Requires Optimize CSS Delivery to be enabled and may need the CSS'
                    . ' Dynamic Selectors setting to be configured to work properly.'
                )
            ],
            [
                'name' => 'Reduce Unused JavaScript',
                'setting' => ($setting = 'pro_reduce_unused_js_enable'),
                'icon' => 'fa fa-file-export',
                'enabled' => $this->params->get($setting, '0'),
                'proonly' => true,
                'class' => 'warning',
                'tooltip' => $this->utility->translate(
                    'Will defer the loading of JavaScript until the user interacts with the page to improve'
                    . ' performance affected by unused JavaScript. If your site uses JavaScript to perform the initial'
                    . ' render you may need to \'exclude\' these critical JavaScript. These will be bundled together,'
                    . ' preloaded and loaded asynchronously.'
                )
            ],
            [
                'name' => 'Reduce DOM',
                'setting' => ($setting = 'pro_reduce_dom'),
                'icon' => 'fa fa-compress-arrows-alt',
                'enabled' => $this->params->get($setting, '0'),
                'proonly' => true,
                'class' => 'warning',
                'tooltip' => $this->utility->translate(
                    '\'Defers\' the loading of some HTML block elements to speed up page rendering.'
                )
            ],
        ];
    }

    public function compileToggleFeaturesIcons($settings): array
    {
        $buttons = [];

        for ($i = 0; $i < count($settings); $i++) {
            //id of figure icon
            $id = $this->generateIdFromName($settings[$i]['name']);
            $setting = $settings[$i]['setting'];
            $nonce = $this->utility->getNonce($setting);
            //script to run when icon is clicked
            $script = <<<JS
onclick="jchPlatform.toggleSetting('{$setting}', '{$id}', '{$nonce}'); return false;"
JS;

            $buttons[$i]['link'] = '';
            $buttons[$i]['icon'] = $settings[$i]['icon'];
            $buttons[$i]['name'] = $this->utility->translate($settings[$i]['name']);
            $buttons[$i]['id'] = $id;
            $buttons[$i]['script'] = $settings[$i]['script'] ?? $script;
            $buttons[$i]['class'] = !empty($settings[$i]['class']) ? [$settings[$i]['class']] : [];
            $buttons[$i]['class'][] = $settings[$i]['enabled'] ? 'enabled' : 'disabled';
            $buttons[$i]['proonly'] = !empty($settings[$i]['proonly']);
            $buttons[$i]['tooltip'] = @$settings[$i]['tooltip'] ?: false;
            $buttons[$i]['enabled'] = (bool)$settings[$i]['enabled'];
        }

        return $buttons;
    }
}
