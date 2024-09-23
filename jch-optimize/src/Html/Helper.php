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

namespace JchOptimize\Html;

use JchOptimize\ContainerFactory;
use JchOptimize\Core\Admin\MultiSelectItems;

use function __;
use function array_key_exists;
use function array_unshift;
use function call_user_func;
use function call_user_func_array;
use function esc_attr_e;
use function explode;
use function get_option;
use function in_array;
use function is_multisite;
use function ucfirst;

class Helper
{
    /**
     * @param   string        $key
     * @param   string        $settingName
     * @param   array|string  $defaultValue
     * @param   mixed         ...$args
     *
     * @return void
     * @psalm-suppress InvalidGlobal
     */
    public static function _(string $key, string $settingName, $defaultValue, ...$args): void
    {
        if (defined('JCH_OPTIMIZE_GET_WORDPRESS_SETTINGS')) {
            global $jchParams;

            $jchParams[$settingName] = $defaultValue;

            return;
        }

        list($function, $proOnly) = static::extract($key);

        if ($proOnly && !JCH_PRO) {
            $html = '<div>
                         <em style="padding: 5px; background-color: white; border: 1px #ccc;">'
                    . __('Only available in Pro Version!', 'jch-optimize')
                    . '  </em>
                    </div>';

            echo $html;

            return;
        }

        $aSavedSettings = get_option('jch-optimize_settings');

        if (!isset($aSavedSettings[$settingName])) {
            $activeValue = $defaultValue;
        } else {
            $activeValue = $aSavedSettings[$settingName];
        }

        $callable = [__CLASS__, $function];

        //prepend $settingName, $activeValue to arguments
        array_unshift($args, $settingName, $activeValue);

        call_user_func_array($callable, $args);
    }

    /**
     * @param   string  $key
     *
     * @return array
     */
    protected static function extract(string $key): array
    {
        $parts = explode('.', $key);

        $function = $parts[0];
        $proOnly = isset($parts[1]) && $parts[1] === 'pro';

        return [$function, $proOnly];
    }

    /**
     * @param   string  $title
     * @param   string  $description
     * @param   bool    $new
     *
     * @return string
     */
    public static function description(string $title, string $description, bool $new = false): string
    {
        $html = '<div class="title">' . $title;

        if ($description) {
            $html .= '<div class="description">
                          <div><p>' . $description . '</p></div>
                      </div>';
        }

        if ($new) {
            $html .= '<span class="badge badge-danger">New!</span>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param   string  $settingName
     * @param   string  $activeValue
     * @param   string  $class
     */
    public static function radio(string $settingName, string $activeValue, string $class = ''): void
    {
        $disabled = ($settingName == 'pro_capture_cache_enable' && is_multisite()) ? 'disabled' : '';
        ?>
        <fieldset id="jch-optimize_settings_<?= $settingName; ?>" class="btn-group <?= $class ?>"
                  role="group" aria-label="Radio toggle button group">
            <input type="radio" id="jch-optimize_settings_<?= $settingName; ?>0"
                   name="<?= "jch-optimize_settings[{$settingName}]"; ?>" class="btn-check" value="0"
                <?= ($activeValue == '0' ? 'checked' : ''); ?> <?= $disabled; ?> >
            <label for="jch-optimize_settings_<?= $settingName ?>0"
                   class="btn btn-outline-secondary"><?php _e('No', 'jch-optimize'); ?></label>
            <input type="radio" id="jch-optimize_settings_<?= $settingName ?>1"
                   name="<?= "jch-optimize_settings[{$settingName}]"; ?>" class="btn-check" value="1"
                <?= ($activeValue == '1' ? 'checked' : ''); ?> <?= $disabled; ?> >
            <label for="jch-optimize_settings_<?= $settingName; ?>1"
                   class="btn btn-outline-secondary"><?php _e('Yes', 'jch-optimize'); ?></label>
        </fieldset>
        <?php
    }

    /**
     * @param   string  $settingName
     * @param   string  $activeValue
     * @param   array   $options
     * @param   string  $class
     * @param   array   $conditions
     */
    public static function select(
        string $settingName,
        string $activeValue,
        array $options,
        string $class = '',
        array $conditions = []
    ): void {
        ?>
        <select id="jch-optimize_settings_<?= $settingName; ?>"
                name="<?= "jch-optimize_settings[{$settingName}]"; ?>"
                class="chzn-custom-value <?= $class; ?>">
            <?= self::option($options, $activeValue, $conditions); ?>
        </select>
        <?php
    }

    /**
     * @param   array<string|int, string>  $options
     * @param   string|null                $activeValue
     * @param   array                      $conditions
     *
     * @return string
     */
    public static function option(array $options, ?string $activeValue, array $conditions = []): string
    {
        $html = '';

        foreach ($options as $key => $value) {
            $selected = $activeValue == $key ? ' selected' : '';
            $disabled = '';

            if (!empty($conditions) && array_key_exists($key, $conditions)) {
                if (!call_user_func($conditions[$key])) {
                    $disabled = ' disabled';
                }
            }

            $html .= '<option value="' . esc_attr($key) . '"'
                     . $selected
                     . $disabled
                     . '>' . $value . '</option>';
        }

        return $html;
    }

    /**
     * @param   string  $settingName
     * @param   array   $activeValues
     * @param   string  $type
     * @param   string  $group
     * @param   string  $class
     */
    public static function multiselect(
        string $settingName,
        array $activeValues,
        string $type,
        string $group,
        string $class = ''
    ): void {
        $container = ContainerFactory::getContainer();
        $multiSelect = $container->buildObject(MultiSelectItems::class);
        ?>
        <select id="jch-optimize_settings_<?= $settingName; ?>"
                name="<?= "jch-optimize_settings[{$settingName}]"; ?>[]"
                class="jch-multiselect chzn-custom-value <?= $class ?>" multiple="multiple" size="5"
                data-jch_type="<?= $type; ?>" data-jch_group="<?= $group; ?>"
                data-jch_param="<?= $settingName; ?>">

            <?php
            foreach ($activeValues as $value) {
                $option = $multiSelect->{'prepare' . ucfirst($group) . 'Values'}($value);
                ?>
                <option value="<?php esc_attr_e($value) ?>" selected><?= $option; ?></option>
                <?php
            }
        ?>
        </select>
        <img id="img-<?= $settingName; ?>" class="jch-multiselect-loading-image"
             src="<?= JCH_PLUGIN_URL . 'media/core/images/exclude-loader.gif'; ?>"/>
        <button id="btn-<?= $settingName; ?>" style="display: none;"
                class="btn btn-secondary btn-sm jch-multiselect-add-button" type="button"
                onmousedown="jchMultiselect.addJchOption('jch-optimize_settings_<?= $settingName; ?>')"><?php _e(
                    'Add item',
                    'jch-optimize'
                ); ?></button>
        <?php
    }

    /**
     * @param   string  $settingName
     * @param   array   $activeValues
     * @param   string  $type
     * @param   string  $group
     * @param   string  $valueType
     * @param   string  $class
     */
    public static function multiselectjs(
        string $settingName,
        array $activeValues,
        string $type,
        string $group,
        string $valueType,
        string $class = ''
    ): void {
        $container = ContainerFactory::getContainer();
        $multiSelect = $container->buildObject(MultiSelectItems::class);
        $nextIndex = count($activeValues);
        $i = 0;
        ?>
        <fieldset id="fieldset-<?= $settingName; ?>" data-index="<?= $nextIndex; ?>">
            <div class="jch-js-fieldset-children jch-js-excludes-header">
                <span class="jch-js-ieo-header">&nbsp;&nbsp;Ignore execution order&nbsp;&nbsp;&nbsp;</span>
                <span class="jch-js-dontmove-header">&nbsp;&nbsp;&nbsp;Don't move to bottom&nbsp;&nbsp;</span>
            </div>
            <?php
            foreach ($activeValues as $value) {
                if (!isset($value[$valueType]) || !is_string($value[$valueType])) {
                    continue;
                }

                $ieoChecked = isset($value['ieo']) ? 'checked' : '';
                $dontMoveChecked = isset($value['dontmove']) ? 'checked' : '';
                /** @var string $dataValue */
                $dataValue = $multiSelect->{'prepare' . ucfirst($group) . 'Values'}($value[$valueType]);
                ?>
                <div id="div-<?= $settingName; ?>-<?= $i; ?>"
                     class="jch-js-fieldset-children jch-js-excludes-container">
                        <span class="jch-js-excludes">
                            <span>
                                <input type="text" readonly value="<?php esc_attr_e($value[$valueType]); ?>"
                                       name="<?= "jch-optimize_settings[{$settingName}][{$i}][{$valueType}]"; ?>">
                                       <?= $dataValue; ?>
                                <button type="button" class="jch-multiselect-remove-button"
                                        onmouseup="jchMultiselect.removeJchJsOption('div-<?= $settingName; ?>-<?= $i; ?>', 'jch-optimize_settings_<?= $settingName; ?>')"></button>
                            </span>
                        </span>
                    <span class="jch-js-ieo">
                            <input type="checkbox"
                                   name="<?= "jch-optimize_settings[{$settingName}][{$i}][ieo]"; ?>" <?= $ieoChecked; ?>>
                        </span>
                    <span class="jch-js-dontmove">
                            <input type="checkbox"
                                   name="<?= "jch-optimize_settings[{$settingName}][{$i}][dontmove]"; ?>" <?= $dontMoveChecked; ?>>
                        </span>
                </div>
                <?php
                $i++;
            }
        ?>
        </fieldset>
        <select id="jch-optimize_settings_<?= $settingName; ?>"
                name="<?= "jch-optimize_settings[{$settingName}]"; ?>[]"
                class="jch-multiselect chzn-custom-value <?= $class ?>" multiple="multiple" size="5"
                data-jch_type="<?= $type; ?>" data-jch_group="<?= $group; ?>"
                data-jch_param="<?= $settingName; ?>">
        </select>
        <img id="img-<?= $settingName; ?>" class="jch-multiselect-loading-image"
             src="<?= JCH_PLUGIN_URL . 'media/core/images/exclude-loader.gif'; ?>"/>
        <button id="btn-<?= $settingName; ?>" style="display: none;"
                class="btn btn-secondary btn-sm jch-multiselect-add-button" type="button"
                onmousedown="jchMultiselect.addJchJsOption('jch-optimize_settings_<?= $settingName; ?>', '<?= $settingName; ?>', '<?= $valueType; ?>')"><?php _e(
                    'Add item',
                    'jch-optimize'
                ); ?></button>
        <script>
            jQuery('#jch-optimize_settings_<?= $settingName; ?>').on('change', function (evt, params) {
                jchMultiselect.appendJchJsOption('jch-optimize_settings_<?= $settingName; ?>', '<?= $settingName; ?>', params, '<?= $valueType; ?>')
            })
        </script>
        <?php
    }

    /**
     * @param   string  $settingName
     * @param   string  $activeValue
     * @param   string  $size
     * @param   string  $class
     */
    public static function text(string $settingName, string $activeValue, string $size = '30', string $class = ''): void
    {
        ?>
        <input type="text" id="jch-optimize_settings_<?= $settingName; ?>"
               name="<?= "jch-optimize_settings[{$settingName}]"; ?>"
               value="<?php esc_attr_e($activeValue); ?>" size="<?= $size; ?>" class="<?= $class; ?>">
        <?php
    }

    public static function hidden(string $settingName, string $activeValue): void
    {
        ?>
        <input type="hidden" id="jch-optimize_settings_<?= $settingName; ?>"
               name="<?= "jch-optimize_settings[{$settingName}]"; ?>"
               value="<?php esc_attr_e($activeValue); ?>">
        <?php
    }

    public static function input(
        string $settingName,
        string $activeValue,
        string $type = 'text',
        array $attr = []
    ): void {
        ?>
        <input type="<?= $type; ?>"
               id="jch-optimize_settings_<?= $settingName; ?>"
               name="<?= "jch-optimize_settings[{$settingName}]"; ?>"
               value="<?php esc_attr_e($activeValue); ?>"
            <?= self::attrArrayToString($attr); ?>>

        <?php
    }

    private static function attrArrayToString(array $attr): string
    {
        $attrString = '';

        foreach ($attr as $name => $value) {
            $attrString .= $name . '="' . $value . '" ';
        }

        return $attrString;
    }

    /**
     * @param   string  $settingName
     * @param   string  $activeValue
     * @param   string  $class
     */
    public static function checkbox(string $settingName, string $activeValue, string $class = ''): void
    {
        $checked = $activeValue == '1' ? 'checked="checked"' : '';
        ?>
        <input type="checkbox" id="jch-optimize_settings_<?= $settingName; ?>" class="<?= $class; ?>"
               name="<?= "jch-optimize_settings[{$settingName}]"; ?>" data-toggle="toggle"
               data-onstyle="success" data-offstyle="danger" data-on="<?php _e('Yes', 'jch-optimize'); ?>"
               data-off="<?php _e('No', 'jch-optimize'); ?>" value="1" <?= $checked; ?>>
        <?php
    }

    /**
     * @param   string                 $settingName
     * @param   array                  $activeValues
     * @param   array<string, string>  $options
     * @param   string                 $class
     */
    public static function checkboxes(
        string $settingName,
        array $activeValues,
        array $options,
        string $class = ''
    ): void {
        $i = '0';
        ?>
        <fieldset id="jch-optimize_settings_<?= $settingName; ?>" class="<?= $class; ?>">
            <ul>
                <?php
                foreach ($options as $key => $value) {
                    $checked = (in_array($key, $activeValues)) ? 'checked' : '';
                    ?>
                    <li>
                        <input type="checkbox" id="jch-optimize_settings_<?= $settingName . $i; ?>"
                               name="<?= "jch-optimize_settings[{$settingName}]"; ?>[]"
                               value="<?php esc_attr_e($key); ?>" <?= $checked; ?>>
                        <label for="jch-optimize_settings_<?= $settingName . $i; ?>">
                            <?= $value; ?>
                        </label>

                    </li>
                    <?php
                    $i++;
                }
        ?>
            </ul>
        </fieldset>
        <?php
    }

    public static function textarea(string $settingName, $activeValues): void
    {
        ?>
        <textarea name="<?= "jch-optimize_settings[{$settingName}]"; ?>" cols="35" rows="3">
        <?= $activeValues ?>
        </textarea>
        <?php
    }
}
