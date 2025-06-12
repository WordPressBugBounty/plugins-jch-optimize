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

namespace JchOptimize\WordPress\Html;

use JchOptimize\Core\Admin\MultiSelectItems;
use JchOptimize\Core\FeatureHelpers\ResponsiveImages;
use JchOptimize\WordPress\Container\ContainerFactory;

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
    public static function _(string $key, string $settingName, array|string $defaultValue, ...$args): void
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

    protected static function extract(string $key): array
    {
        $parts = explode('.', $key);

        $function = $parts[0];
        $proOnly = isset($parts[1]) && $parts[1] === 'pro';

        return [$function, $proOnly];
    }

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

    public static function switch(string $settingName, string $activeValue, string $class = ''): void
    {
        $disabled = ($settingName == 'pro_capture_cache_enable' && is_multisite()) ? 'disabled' : '';
        ?>
        <div class="form-check form-switch d-flex align-items-center <?= $class ?>">
            <input class="form-check-input me-2" type="checkbox" role="switch" id="jch-optimize_settings_<?= $settingName; ?>"
            name="<?= "jch-optimize_settings[{$settingName}]"; ?>" <?= $activeValue == '1' ? 'checked' : ''; ?> value="1" <?= $disabled; ?>>
            <label class="form-check-label" for="jch-optimize_settings_<?= $settingName; ?>">
                <?php $activeValue == '1' ? _e('Yes', 'jch-optimize') : _e('No', 'jch-optimize'); ?>
             </label>
            <?php if ($activeValue != '1') : ?>
            <input type="hidden" name="<?= "jch-optimize_settings[{$settingName}]"; ?>" value="0" >
            <?php endif; ?>
            <script>
    document.getElementById('jch-optimize_settings_<?=  $settingName; ?>').addEventListener('click', (e) => {
        const label = document.querySelector('label[for="jch-optimize_settings_<?= $settingName; ?>"]');
        if (e.target.checked) {
            label.innerText = "<?php _e('Yes', 'jch-optimize'); ?>";
            const input = document.querySelector('input[type="hidden"][name="jch-optimize_settings[<?= $settingName; ?>]"]');
            if (input) {
                input.remove();
            }
        } else {
            label.innerText = "<?php _e('No', 'jch-optimize'); ?>";
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'jch-optimize_settings[<?= $settingName; ?>]';
            input.value = '0';
            label.after(input);
        }
    });
            </script>
        </div>

            <?php
    }
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

    public static function multiselect(
        string $settingName,
        array $activeValues,
        string $type,
        string $group,
        string $class = ''
    ): void {
        $container = ContainerFactory::getInstance();
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

    public static function multiselectjs(
        string $settingName,
        array $activeValues,
        string $type,
        string $group,
        string $valueType,
        string $option1 = 'ieo',
        string $option2 = 'dontmove',
        string $title1 = 'Ignore execution order',
        string $title2 = 'Don\'t move to bottom',
        string $option1Obj = '',
        string $option2Obj = '',
        string $subFieldClass = '',
        string $class = '',
        string $option1SubField = 'checkbox',
        string $option2SubField = 'checkbox',
        string $option1ParentClass = 'jch-js-ieo',
        string $option2ParentClass = 'jch-js-dontmove',
    ): void {
        $container = ContainerFactory::getInstance();
        $multiSelect = $container->buildObject(MultiSelectItems::class);
        $nextIndex = count($activeValues);
        $i = 0;
        static $incrementor = 0;
        $inc1 = ++ $incrementor;
        $inc2 = ++ $incrementor;
        $option1Obj = $option1Obj ?: "{type: \"checkbox\", name: \"{$option1}\", class: \"{$subFieldClass}\"}";
        $option2Obj = $option2Obj ?: "{type: \"checkbox\", name: \"{$option2}\", class: \"{$subFieldClass}\"}";
        ?>
<script>
    let optionObj<?= $inc1 ?> = <?= $option1Obj; ?>;
    let optionObj<?= $inc2 ?> = <?= $option2Obj ?>;
</script>
        <fieldset id="fieldset-<?= $settingName; ?>" data-index="<?= $nextIndex; ?>">
            <div class="jch-js-fieldset-children jch-js-excludes-header">
                <span class="jch-js-ieo-header">&nbsp;&nbsp;<?= $title1 ?>&nbsp;&nbsp;&nbsp;</span>
                <span class="jch-js-dontmove-header">&nbsp;&nbsp;&nbsp;<?= $title2 ?>&nbsp;&nbsp;</span>
            </div>
            <?php foreach ($activeValues as $value) : ?>
                <?php if (isset($value[$valueType]) && is_string($value[$valueType])) : ?>
            <?php
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
                    <?php self::subFormField($option1SubField, $option1ParentClass, $subFieldClass, $value, $settingName, $i, $option1); ?>
                    <?php self::subFormField($option2SubField, $option2ParentClass, $subFieldClass, $value, $settingName, $i, $option2); ?>
                </div>
                <?php $i++; ?>
           <?php endif; ?>
        <?php endforeach; ?>
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
                onmousedown="jchMultiselect.addJchJsOption('jch-optimize_settings_<?= $settingName; ?>', '<?= $settingName; ?>', '<?= $valueType; ?>', optionObj<?= $inc1; ?>, optionObj<?= $inc2; ?>)"><?php _e(
                    'Add item',
                    'jch-optimize'
                ); ?></button>
<script>
    jQuery('#jch-optimize_settings_<?= $settingName; ?>').on('change', function (evt, params) {
        jchMultiselect.appendJchJsOption('jch-optimize_settings_<?= $settingName; ?>', '<?= $settingName; ?>', params, '<?= $valueType; ?>', optionObj<?= $inc1 ?>, optionObj<?= $inc2 ?>)
    })
</script>
        <?php
    }

    private static function subFormField(
        string $type,
        string $parentClass,
        string $subFieldClass,
        array $value,
        string $settingName,
        int $index,
        string $option
    ): void {
        if ($type == 'checkbox') :
            ?>
        <span class="<?= $parentClass ?>">
            <input type="checkbox" class="<?= $subFieldClass ?>"
                   name="<?= "jch-optimize_settings[{$settingName}][{$index}][{$option}]" ?>"
                   <?= isset($value[$option]) ? 'checked' : ''?>>
        </span>
            <?php
        elseif ($type == 'text'):
            ?>
        <span class="<?= $parentClass ?> has-text-input">
           <input type="text" name="jch-optimize_settings[<?= $settingName ?>][<?= $index ?>][<?= $option ?>]"
                  value="<?= $value[$option] ?? ResponsiveImages::$breakpoints[0] ?>" >
        </span>
            <?php
        elseif ($type == 'select') :
            ?>
        <span class="<?= $parentClass ?> has-select">
            <select name="jch-optimize_settings[<?=  $settingName ?>][<?= $index ?>][<?= $option ?>]">
                <option value="West" <?= $value[$option] == 'West' ? 'selected' : '' ?>>Left</option>
                <option value="Center" <?= $value[$option] == 'Center' ? 'selected' : '' ?>>Center</option>
                <option value="East" <?= $value[$option] == 'East' ? 'selected' : '' ?>>Right</option>
            </select>
        </span>
        <?php
        endif;
    }

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

    public static function checkboxes(
        string $settingName,
        array $activeValues,
        array $options,
        string $class = ''
    ): void {
        $i = '0';
        $cols = count($options);
        if ($cols > 7) {
            $cols = ceil($cols / 2);
        }

        ?>
        <fieldset id="jch-optimize_settings_<?= $settingName; ?>" class="<?= $class; ?> float-start">
            <ul class="grid" style="--jch-bs-columns: <?= $cols ?>">
                <?php
                foreach ($options as $key => $value):
                    $checked = (in_array($key, $activeValues)) ? 'checked' : '';
                    ?>
                    <li class="g-col-1">
                        <input type="checkbox" id="jch-optimize_settings_<?= $settingName . $i; ?>"
                               name="<?= "jch-optimize_settings[{$settingName}]"; ?>[]"
                               value="<?php esc_attr_e($key); ?>" <?= $checked; ?>>
                        <label for="jch-optimize_settings_<?= $settingName . $i; ?>">
                            <?= $value; ?>
                        </label>

                    </li>
                    <?php
                    $i++;
                endforeach;
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

    public static function criticaljsmodalbutton(string $settingName, $activeValue): void
    {
        ?>
<button type="button" class="button button-primary button-large" id="criticalJsModalLaunchButton">
Open
</button>
</div>
<?php
    }
}
