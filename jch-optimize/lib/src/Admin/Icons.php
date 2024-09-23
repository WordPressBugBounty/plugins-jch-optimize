<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads.
 *
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Admin;

use _JchOptimizeVendor\Joomla\CMS\Language\Text;
use _JchOptimizeVendor\Joomla\DI\ContainerAwareInterface;
use JchOptimize\Core\Container\ContainerAwareTrait;
use JchOptimize\Core\Registry;
use JchOptimize\Model\ModeSwitcher;
use JchOptimize\Platform\Cache;
use JchOptimize\Platform\Paths;
use JchOptimize\Platform\Utility;

\defined('_JCH_EXEC') or exit('Restricted access');
class Icons implements ContainerAwareInterface
{
    use ContainerAwareTrait;
    private Registry $params;

    public function __construct(Registry $params)
    {
        $this->params = $params;
    }

    public static function getAutoSettingsArray(): array
    {
        return [['name' => 'Minimum', 'icon' => 'minimum.png', 'setting' => 1], ['name' => 'Intermediate', 'icon' => 'intermediate.png', 'setting' => 2], ['name' => 'Average', 'icon' => 'average.png', 'setting' => 3], ['name' => 'Deluxe', 'icon' => 'deluxe.png', 'setting' => 4], ['name' => 'Premium', 'icon' => 'premium.png', 'setting' => 5], ['name' => 'Optimum', 'icon' => 'optimum.png', 'setting' => 6]];
    }

    public function printIconsHTML($buttons): string
    {
        $sIconsHTML = '';
        foreach ($buttons as $button) {
            $sContentAttr = Utility::bsTooltipContentAttribute();
            $sTooltip = @$button['tooltip'] ? " class=\"hasPopover fig-caption\" title=\"{$button['name']}\" {$sContentAttr}=\"{$button['tooltip']}\" " : ' class="fig-caption"';
            $sIconSrc = Paths::iconsUrl().'/'.$button['icon'];
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
\t<a href="{$button['link']}" class="btn" {$button['script']}{$onClickFalse}>
\t\t<img src="{$sIconSrc}" alt="" width="50" height="50" />
\t\t<span{$sTooltip}>{$button['name']}</span>
\t\t{$sToggle}
\t</a>
</figure>

HTML;
        }

        return $sIconsHTML;
    }

    public function compileAutoSettingsIcons($settings): array
    {
        $buttons = [];
        for ($i = 0; $i < \count($settings); ++$i) {
            $id = $this->generateIdFromName($settings[$i]['name']);
            $buttons[$i]['link'] = '';
            $buttons[$i]['icon'] = $settings[$i]['icon'];
            $buttons[$i]['name'] = $settings[$i]['name'];
            $buttons[$i]['script'] = "onclick=\"jchPlatform.applyAutoSettings('{$settings[$i]['setting']}', '{$id}', '".Utility::getNonce('s'.$settings[$i]['setting'])."'); return false;\"";
            $buttons[$i]['id'] = $id;
            $buttons[$i]['class'] = 'auto-setting disabled';
            $buttons[$i]['tooltip'] = \htmlspecialchars(self::generateAutoSettingTooltip($settings[$i]['setting']));
        }
        $sCombineFilesEnable = $this->params->get('combine_files_enable', '0');
        $aParamsArray = $this->params->toArray();
        $aAutoSettings = self::autoSettingsArrayMap();
        $aAutoSettingsInit = \array_map(function ($a) {
            return '0';
        }, $aAutoSettings);
        $aCurrentAutoSettings = \array_intersect_key($aParamsArray, $aAutoSettingsInit);
        // order array
        $aCurrentAutoSettings = \array_merge($aAutoSettingsInit, $aCurrentAutoSettings);
        if ($sCombineFilesEnable) {
            for ($j = 0; $j < 6; ++$j) {
                if (\array_values($aCurrentAutoSettings) === \array_column($aAutoSettings, 's'.($j + 1))) {
                    $buttons[$j]['class'] = 'auto-setting enabled';

                    break;
                }
            }
        }

        return $buttons;
    }

    public static function autoSettingsArrayMap(): array
    {
        return ['css' => ['s1' => '1', 's2' => '1', 's3' => '1', 's4' => '1', 's5' => '1', 's6' => '1'], 'javascript' => ['s1' => '1', 's2' => '1', 's3' => '1', 's4' => '1', 's5' => '1', 's6' => '1'], 'gzip' => ['s1' => '0', 's2' => '1', 's3' => '1', 's4' => '1', 's5' => '1', 's6' => '1'], 'css_minify' => ['s1' => '0', 's2' => '1', 's3' => '1', 's4' => '1', 's5' => '1', 's6' => '1'], 'js_minify' => ['s1' => '0', 's2' => '1', 's3' => '1', 's4' => '1', 's5' => '1', 's6' => '1'], 'html_minify' => ['s1' => '0', 's2' => '1', 's3' => '1', 's4' => '1', 's5' => '1', 's6' => '1'], 'includeAllExtensions' => ['s1' => '0', 's2' => '0', 's3' => '1', 's4' => '1', 's5' => '1', 's6' => '1'], 'replaceImports' => ['s1' => '0', 's2' => '0', 's3' => '0', 's4' => '1', 's5' => '1', 's6' => '1'], 'phpAndExternal' => ['s1' => '0', 's2' => '0', 's3' => '0', 's4' => '1', 's5' => '1', 's6' => '1'], 'inlineStyle' => ['s1' => '0', 's2' => '0', 's3' => '0', 's4' => '1', 's5' => '1', 's6' => '1'], 'inlineScripts' => ['s1' => '0', 's2' => '0', 's3' => '0', 's4' => '1', 's5' => '1', 's6' => '1'], 'bottom_js' => ['s1' => '0', 's2' => '0', 's3' => '0', 's4' => '0', 's5' => '1', 's6' => '1'], 'loadAsynchronous' => ['s1' => '0', 's2' => '0', 's3' => '0', 's4' => '0', 's5' => '0', 's6' => '1']];
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
        $aUtilities = [$action = 'browsercaching' => ['action' => $action, 'icon' => 'browser_caching.png', 'name' => 'Optimize .htaccess', 'tooltip' => Utility::translate('Use this button to add codes to your htaccess file to enable leverage browser caching and gzip compression.')], $action = 'filepermissions' => ['action' => $action, 'icon' => 'file_permissions.png', 'name' => 'Fix file permissions', 'tooltip' => Utility::translate("If your site has lost CSS formatting after enabling the plugin, the problem could be that the plugin files were installed with incorrect file permissions so the browser cannot access the cached combined file. Click here to correct the plugin's file permissions.")], $action = 'cleancache' => ['action' => $action, 'icon' => 'clean_cache.png', 'name' => 'Clean Cache', 'tooltip' => Utility::translate("Click this button to clean the plugin's cache and page cache. If you have edited any CSS or JavaScript files you need to clean the cache so the changes can be visible.")], $action = 'orderplugins' => ['action' => $action, 'icon' => 'order_plugin.png', 'name' => 'Order Plugin', 'tooltip' => Utility::translate('The published order of the plugin is important! When you click on this icon, it will attempt to order the plugin correctly.')], $action = 'keycache' => ['action' => $action, 'icon' => 'keycache.png', 'name' => 'Generate new cache key', 'tooltip' => Utility::translate("If you've made any changes to your files generate a new cache key to counter browser caching of the old content.")], $action = 'recache' => ['action' => $action, 'icon' => 'recache.png', 'name' => 'Recache', 'proonly' => \true, 'tooltip' => Utility::translate('Rebuild the cache for all the pages of the site.')], $action = 'bulksettings' => ['action' => $action, 'icon' => 'bulk_settings.png', 'name' => 'Bulk Setting Operations', 'tooltip' => Utility::translate('Opens a modal that provides options to import/export settings, or restore to default values.'), 'script' => 'onclick="loadBulkSettingsModal(); return false;"'], $action = 'restoreimages' => ['action' => $action, 'icon' => 'restoreimages.png', 'name' => 'Restore Original Images,', 'tooltip' => Utility::translate("If you're not satisfied with the images that were optimized you can restore the original ones by clicking this button if they were not deleted. This will also remove any webp image created from the restored file."), 'proonly' => \true], $action = 'deletebackups' => ['action' => $action, 'icon' => 'deletebackups.png', 'name' => 'Delete Backup Images', 'tooltip' => Utility::translate("This will permanently delete the images that were backed up. There's no way to undo this so be sure you're satisfied with the ones that were optimized before clicking this button."), 'proonly' => \true, 'script' => 'onclick="return confirm(\'Are you sure? This cannot be undone!\');"']];
        if (empty($actions)) {
            return $aUtilities;
        }

        return \array_intersect_key($aUtilities, \array_flip($actions));
    }

    public function compileUtilityIcons($utilities): array
    {
        $icons = [];
        $i = 0;
        foreach ($utilities as $utility) {
            $icons[$i]['link'] = Paths::adminController($utility['action']);
            $icons[$i]['icon'] = $utility['icon'];
            $icons[$i]['name'] = Utility::translate($utility['name']);
            $icons[$i]['id'] = $this->generateIdFromName($utility['name']);
            $icons[$i]['tooltip'] = @$utility['tooltip'] ?: \false;
            $icons[$i]['script'] = @$utility['script'] ?: '';
            $icons[$i]['class'] = '';
            $icons[$i]['proonly'] = @$utility['proonly'] ?: \false;
            ++$i;
        }

        return $icons;
    }

    public function getToggleSettings(): array
    {
        $pageCacheTooltip = '';
        if (\JCH_PLATFORM == 'Joomla!') {
            $pageCacheTooltip = '<strong>[';
            if (JCH_PRO) {
                $modeSwitcher = $this->container->get(ModeSwitcher::class);
                $integratedPageCache = $modeSwitcher->getIntegratedPageCachePlugin();
                $pageCacheTooltip .= Text::_($modeSwitcher->pageCachePlugins[$integratedPageCache]);
            } else {
                $pageCacheTooltip .= Text::_('COM_JCHOPTIMIZE_SYSTEM_PAGE_CACHE');
            }
            $pageCacheTooltip .= ']</strong><br><br>';
        }
        $pageCacheTooltip .= Utility::translate('Toggles on/off the Page Cache feature.');

        return [['name' => 'Add Image Attributes', 'setting' => $setting = 'img_attributes_enable', 'icon' => 'img_attributes.png', 'enabled' => $this->params->get($setting, '0'), 'tooltip' => Utility::translate('Adds \'height\' and/or \'width\' attributes to &lt:img&gt;\'s, if missing, to reduce CLS.')], ['name' => 'Sprite Generator', 'setting' => $setting = 'csg_enable', 'icon' => 'sprite_gen.png', 'enabled' => $this->params->get($setting, '0'), 'tooltip' => Utility::translate('Combines select background images into a sprite.')], ['name' => 'Http/2 Push', 'setting' => $setting = 'http2_push_enable', 'icon' => 'http2_push.png', 'enabled' => $this->params->get($setting, '0'), 'tooltip' => Utility::translate('Preloads critical assets using the http/2 protocol to improve LCP.')], ['name' => 'Lazy Load Images', 'setting' => $setting = 'lazyload_enable', 'icon' => 'lazyload.png', 'enabled' => $this->params->get($setting, '0'), 'tooltip' => Utility::translate('Defer images that fall below the fold.')], ['name' => 'Optimize CSS Delivery', 'setting' => $setting = 'optimizeCssDelivery_enable', 'icon' => 'optimize_css_delivery.png', 'enabled' => $this->params->get($setting, '0'), 'tooltip' => Utility::translate('Eliminates CSS render-blocking')], ['name' => 'Optimize Fonts', 'setting' => $setting = 'pro_optimizeFonts_enable', 'icon' => 'optimize_gfont.png', 'enabled' => $this->params->get($setting, '0'), 'proonly' => \true, 'tooltip' => Utility::translate('Optimizes the loading of fonts, including Google Fonts.')], ['name' => 'CDN', 'setting' => $setting = 'cookielessdomain_enable', 'icon' => 'cdn.png', 'enabled' => $this->params->get($setting, '0'), 'tooltip' => Utility::translate('Loads static assets from a CDN server. Requires the CDN domain(s) to be configured on the Configuration tab.')], ['name' => 'Smart Combine', 'setting' => $setting = 'pro_smart_combine', 'icon' => 'smart_combine.png', 'enabled' => $this->params->get($setting, '0'), 'proonly' => \true, 'tooltip' => Utility::translate('Intelligently combines files in a number of smaller files, instead of one large file for better http2 delivery.')], ['name' => 'Load Webp', 'setting' => $setting = 'pro_load_webp_images', 'icon' => 'webp.png', 'enabled' => $this->params->get($setting, '0'), 'proonly' => \true, 'tooltip' => Utility::translate('Loads generated WEBP images in place of the original ones. These images must be generated on the Optimize Image tab first.')], ['name' => 'Load Responsive', 'setting' => $setting = 'pro_load_responsive_images', 'icon' => 'responsive_images.png', 'enabled' => $this->params->get($setting, '0'), 'proonly' => \true, 'tooltip' => Utility::translate('Use responsive images where available. These images must be generated on the Optimize Image tab first.')], ['name' => 'LCP Images', 'setting' => $setting = 'pro_lcp_images_enable', 'icon' => 'lcp_images.png', 'enabled' => $this->params->get($setting, '0'), 'proonly' => \true, 'tooltip' => Utility::translate('Preload LCP images with a high fetch priority. These images must be added on the Options page to be discovered.')], ['name' => 'Preconnects', 'setting' => $setting = 'pro_preconnect_domains_enable', 'icon' => 'preconnect.png', 'enabled' => $this->params->get($setting, '0'), 'proonly' => \true, 'tooltip' => Utility::translate('Preconnect external origins to reduce the impact of third-party domains.')], ['name' => 'Page Cache', 'setting' => 'integrated_page_cache_enable', 'icon' => 'cache.png', 'enabled' => Cache::isPageCacheEnabled($this->params), 'tooltip' => $pageCacheTooltip]];
    }

    public function getCombineFilesEnableSetting(): array
    {
        return [['name' => 'Combine Files Enable', 'setting' => $setting = 'combine_files_enable', 'icon' => 'combine_files_enable.png', 'enabled' => $this->params->get($setting, '1')]];
    }

    public function getAdvancedToggleSettings(): array
    {
        return [['name' => 'Reduce Unused CSS', 'setting' => $setting = 'pro_reduce_unused_css', 'icon' => 'reduce_unused_css.png', 'enabled' => $this->params->get($setting, '0'), 'proonly' => \true, 'tooltip' => Utility::translate('Loads only the critical CSS required for rendering the page above the fold until user interacts with the page. Requires Optimize CSS Delivery to be enabled and may need the CSS Dynamic Selectors setting to be configured to work properly.')], ['name' => 'Reduce Unused JavaScript', 'setting' => $setting = 'pro_reduce_unused_js_enable', 'icon' => 'reduce_unused_js.png', 'enabled' => $this->params->get($setting, '0'), 'proonly' => \true, 'tooltip' => Utility::translate('Will defer the loading of JavaScript until the user interacts with the page to improve performance affected by unused JavaScript. If your site uses JavaScript to perform the initial render you may need to \'exclude\' these critical JavaScript. These will be bundled together, preloaded and loaded asynchronously.')], ['name' => 'Reduce DOM', 'setting' => $setting = 'pro_reduce_dom', 'icon' => 'reduce_dom.png', 'enabled' => $this->params->get($setting, '0'), 'proonly' => \true, 'tooltip' => Utility::translate('\'Defers\' the loading of some HTML block elements to speed up page rendering.')]];
    }

    public function compileToggleFeaturesIcons($settings): array
    {
        $buttons = [];
        for ($i = 0; $i < \count($settings); ++$i) {
            // id of figure icon
            $id = $this->generateIdFromName($settings[$i]['name']);
            $setting = $settings[$i]['setting'];
            $nonce = Utility::getNonce($setting);
            // script to run when icon is clicked
            $script = <<<JS
onclick="jchPlatform.toggleSetting('{$setting}', '{$id}', '{$nonce}'); return false;"
JS;
            $buttons[$i]['link'] = '';
            $buttons[$i]['icon'] = $settings[$i]['icon'];
            $buttons[$i]['name'] = Utility::translate($settings[$i]['name']);
            $buttons[$i]['id'] = $id;
            $buttons[$i]['script'] = $script;
            $buttons[$i]['class'] = $settings[$i]['enabled'] ? 'enabled' : 'disabled';
            $buttons[$i]['proonly'] = !empty($settings[$i]['proonly']);
            $buttons[$i]['tooltip'] = @$settings[$i]['tooltip'] ?: \false;
        }

        return $buttons;
    }

    private function generateIdFromName($name): string
    {
        return \strtolower(\str_replace([' ', '/'], ['-', ''], \trim($name)));
    }

    private function generateAutoSettingTooltip($setting): string
    {
        $aAutoSettingsMap = self::autoSettingsArrayMap();
        $aCurrentSettingValues = \array_column($aAutoSettingsMap, 's'.$setting);
        $aCurrentSettingArray = \array_combine(\array_keys($aAutoSettingsMap), $aCurrentSettingValues);
        $aSetting = \array_map(function ($v) {
            return '1' == $v ? 'on' : 'off';
        }, $aCurrentSettingArray);

        return <<<HTML
<h4 class="list-header">CSS</h4>
<ul class="unstyled list-unstyled">
<li>Combine CSS <i class="toggle fa {$aSetting['css']}"></i></li>
<li>Minify CSS <i class="toggle fa {$aSetting['css_minify']}"></i></li>
<li>Resolve @imports <i class="toggle fa {$aSetting['replaceImports']}"></i></li>
<li>Include in-page styles <i class="toggle fa {$aSetting['inlineStyle']}"></i></li>
</ul>
<h4 class="list-header">JavaScript</h4>
<ul class="unstyled list-unstyled">
<li>Combine JavaScript <i class="toggle fa {$aSetting['javascript']}"></i></li>
<li>Minify JavaScript <i class="toggle fa {$aSetting['js_minify']}"></i></li>
<li>Include in-page scripts <i class="toggle fa {$aSetting['inlineScripts']}"></i></li>
<li>Place JavaScript at bottom <i class="toggle fa {$aSetting['bottom_js']}"></i></li>
<li>Defer/Async JavaScript <i class="toggle fa {$aSetting['loadAsynchronous']}"></i></li>
</ul>
<h4 class="list-header">Combine files</h4>
<ul class="unstyled list-unstyled">
<li>Gzip JavaScript/CSS <i class="toggle fa {$aSetting['gzip']}"></i> </li>
<li>Minify HTML <i class="toggle fa {$aSetting['html_minify']}"></i> </li>
<li>Include third-party files <i class="toggle fa {$aSetting['includeAllExtensions']}"></i></li>
<li>Include external files <i class="toggle fa {$aSetting['phpAndExternal']}"></i></li>
</ul>
HTML;
    }
}
