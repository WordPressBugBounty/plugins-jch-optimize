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
use function htmlspecialchars;
use function in_array;
use function is_array;
use function is_multisite;
use function ucfirst;
use function wp_create_nonce;

use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

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
        $popOver = $description
            ? "class=\"hasPopover pe-1\" data-bs-content=\"$description\" data-bs-title=\"$title\""
            : '';
        $html = "<div $popOver style=\"width: fit-content;\">" . $title;

        if ($description) {
            $html .= '<span>
                          <span class="far fa-question-circle text-muted opacity-75 ms-1"></span>
                      </span>';
        }

        if ($new) {
            $html .= '<span class="badge bg-danger ms-1">New!</span>';
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
            <label for="jch-optimize_settings_<?= $settingName ?>0" class="btn btn-outline-secondary">
                <?php _e('No', 'jch-optimize'); ?>
            </label>
            <input type="radio" id="jch-optimize_settings_<?= $settingName ?>1"
                   name="<?= "jch-optimize_settings[{$settingName}]"; ?>" class="btn-check" value="1"
                <?= ($activeValue == '1' ? 'checked' : ''); ?> <?= $disabled; ?> >
            <label for="jch-optimize_settings_<?= $settingName; ?>1" class="btn btn-outline-secondary">
                <?php _e('Yes', 'jch-optimize'); ?>
            </label>
        </fieldset>
        <?php
    }

    public static function switch(string $settingName, string $activeValue, string $class = ''): void
    {
        $disabled = ($settingName == 'pro_capture_cache_enable' && is_multisite()) ? 'disabled' : '';
        ?>
        <div class="form-check form-switch d-flex align-items-center <?= $class ?>">
            <input class="form-check-input me-2" type="checkbox" role="switch"
                   id="jch-optimize_settings_<?= $settingName; ?>"
                   name="<?= "jch-optimize_settings[{$settingName}]"; ?>" <?= $activeValue == '1' ? 'checked' : ''; ?>
                   value="1" <?= $disabled; ?>>
            <label class="form-check-label" for="jch-optimize_settings_<?= $settingName; ?>">
                <?php
                $activeValue == '1' ? _e('Yes', 'jch-optimize') : _e('No', 'jch-optimize'); ?>
            </label>
            <?php
            if ($activeValue != '1') : ?>
                <input type="hidden" name="<?= "jch-optimize_settings[{$settingName}]"; ?>" value="0">
            <?php
            endif; ?>
            <script>
                document.getElementById('jch-optimize_settings_<?=  $settingName; ?>').addEventListener('click', (e) => {
                    const label = document.querySelector('label[for="jch-optimize_settings_<?= $settingName; ?>"]')
                    if (e.target.checked) {
                        label.innerText = "<?php _e('Yes', 'jch-optimize'); ?>"
                        const input = document.querySelector('input[type="hidden"][name="jch-optimize_settings[<?= $settingName; ?>]"]')
                        if (input) {
                            input.remove()
                        }
                    } else {
                        label.innerText = "<?php _e('No', 'jch-optimize'); ?>"
                        const input = document.createElement('input')
                        input.type = 'hidden'
                        input.name = 'jch-optimize_settings[<?= $settingName; ?>]'
                        input.value = '0'
                        label.after(input)
                    }
                })
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
                class="<?= $class; ?> form-select">
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
        <div id="div-<?= $settingName ?>">
            <select id="jch-optimize_settings_<?= $settingName; ?>"
                    name="<?= "jch-optimize_settings[{$settingName}]"; ?>[]"
                    class="jch-multiselect chzn-custom-value <?= $class ?>" multiple="multiple" size="5"
                    data-jch_type="<?= $type; ?>" data-jch_group="<?= $group; ?>"
                    data-jch_param="<?= $settingName; ?>">
                <option value="">Type or click to select</option>
                <?php
                foreach ($activeValues as $value) :
                    $option = $multiSelect->{'prepare' . ucfirst($group) . 'Values'}($value);
                    ?>
                    <option value="<?php
                    esc_attr_e($value) ?>" selected><?= $option; ?></option>
                <?php endforeach; ?>
            </select>
            <button id="btn-<?= $settingName; ?>" class="btn btn-secondary btn-sm jch-multiselect-add-button"
                    type="button">Add item
            </button>
        </div>
        <?php
    }

    public static function multiselectjs(
        string $settingName,
        array $activeValues,
        string $type,
        string $group,
        string $valueType,
        array $subfields,
        string $class = ''
    ): void {
        $container = ContainerFactory::getInstance();
        $multiSelect = $container->buildObject(MultiSelectItems::class);
        $nextIndex = count($activeValues);
        $i = 0;
        $jsSubfields = [];
        $subfieldsCount = count($subfields);
        foreach ($subfields as $sf) {
            $cfg = [
                'name' => $sf['name'],
                'type' => $sf['type'],
            ];
            if (!empty($sf['class'])) {
                $cfg['class'] = $sf['class'];
            }
            if (array_key_exists('checked', $sf)) {
                $cfg['checked'] = $sf['checked'];
            }
            if (array_key_exists('defaultValue', $sf)) {
                $cfg['defaultValue'] = $sf['defaultValue'];
            }
            if (!empty($sf['options']) && is_array($sf['options'])) {
                $cfg['options'] = $sf['options'];
            }

            $jsSubfields[] = $cfg;
        }
        $json = json_encode(
            $jsSubfields,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        ?>
        <script type="application/json" id="subfields-<?= $settingName; ?>">
            <?= $json; ?>





        </script>
        <fieldset id="fieldset-<?= $settingName; ?>" data-index="<?= $nextIndex; ?>"
                  data-value-type="<?php esc_attr_e($valueType); ?>"
                  class="mb-1 mt-2 jch-ms-fieldset-grid" style="--jch-ms-subfield-count: <?= $subfieldsCount ?>"
        >
            <?php if ($subfields) : ?>
                <?php foreach ($subfields as $subfield) : ?>
                    <?php if (!empty($subfield['header'])) : ?>
                        <span class="jch-ms-<?php esc_attr_e($subfield['name']); ?>-header jch-ms-cell jch-ms-header">
                    &nbsp;&nbsp;<?= $subfield['header'] ?>&nbsp;&nbsp;&nbsp;
                    </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php foreach ($activeValues as $value) : ?>
                <?php
                if (isset($value[$valueType]) && is_string($value[$valueType])) : ?>
                    <?php
                    $method = 'prepare' . ucfirst($group) . 'Values';
                    if ($multiSelect && method_exists($multiSelect, $method)) {
                        $dataValue = $multiSelect->$method($value[$valueType]);
                    } else {
                        $dataValue = $value[$valueType];
                    }
                    ?>
                    <span class="group<?php esc_attr_e($i) ?> jch-ms-excludes jch-ms-cell">
                            <span>
                                <input type="text" readonly
                                       value="<?php esc_attr_e($value[$valueType]); ?>"
                                       name="<?= "jch-optimize_settings[{$settingName}][{$i}][{$valueType}]"; ?>"
                                >
                                       <?= $dataValue; ?>
                                <button type="button" class="jch-multiselect-remove-button">
                                    <?php _e('Remove Item', 'jch-optimize'); ?>
                                </button>
                            </span>
                        </span>
                    <?php foreach ($subfields as $subfield) : ?>
                        <?php
                        self::subFormField(
                            $subfield['type'],
                            'jch-ms-' . $subfield['name'],
                            $value,
                            $settingName,
                            $i,
                            $subfield['name'],
                            $subfield
                        ); ?>
                    <?php endforeach; ?>
                    <?php $i++; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </fieldset>
        <div id="div-<?= $settingName ?>">
            <select id="jch-optimize_settings_<?= $settingName; ?>"
                    name="<?= "jch-optimize_settings[{$settingName}]"; ?>[]"
                    class="jch-multiselect chzn-custom-value <?= $class ?>" multiple="multiple" size="5"
                    data-jch_type="<?= $type; ?>" data-jch_group="<?= $group; ?>"
                    data-jch_param="<?= $settingName; ?>">
                <option value="">Type or click to select</option>
            </select>
            <button id="btn-<?= $settingName; ?>"
                    class="btn btn-secondary btn-sm jch-multiselect-add-button" type="button"
            ><?php _e('Add item', 'jch-optimize'); ?></button>
        </div>

        <?php
    }

    private static function subFormField(
        string $type,
        string $subFieldClass,
        array $value,
        string $settingName,
        int $index,
        string $option,
        array $config
    ): void {
        if ($type == 'checkbox') :
            ?>
            <span class="group<?php esc_attr_e((string)$index) ?> <?php esc_attr_e(
                $subFieldClass
            ) ?> jch-ms-cell has-subfield has-checkbox">
            <input type="checkbox"
                   class="subfield m-0 p-0 fs-6 form-check-input <?php esc_attr_e($config['class'] ?? '') ?>"
                   name="<?= "jch-optimize_settings[{$settingName}][{$index}][{$option}]" ?>"
                   <?= isset($value[$option]) ? 'checked' : '' ?>>
        </span>
        <?php elseif ($type == 'text') : ?>
            <span class="group<?php esc_attr_e((string)$index) ?> <?php esc_attr_e(
                $subFieldClass
            ) ?> jch-ms-cell has-subfield has-text">
           <input type="text" name="jch-optimize_settings[<?= $settingName ?>][<?= $index ?>][<?= $option ?>]"
                  class="subfield form-control form-control-sm <?php esc_attr_e($config['class'] ?? '') ?>"
                  value="<?= $value[$option] ?? ResponsiveImages::$breakpoints[0] ?>">
        </span>
        <?php elseif ($type == 'select') : ?>
            <?php
            $current = $value[$option] ?? ($config['defaultValue'] ?? null);
            if ($current === null && isset($config['legacy'])) {
                $current = '';
                foreach ($config['legacy'] as $legacyKey) {
                    if (!empty($value[$legacyKey])) {
                        $current = (string)$legacyKey;
                        break;
                    }
                }
            }
            $options = $config['options'] ?? [];
            ?>
            <span class="group<?php esc_attr_e($index); ?> <?php esc_attr_e(
                $subFieldClass
            ) ?> jch-ms-cell has-subfield has-select">
            <select name="jch-optimize_settings[<?= $settingName ?>][<?= $index ?>][<?= $option ?>]"
                    class="subfield form-select form-select-sm <?php esc_attr_e($config['class'] ?? '') ?>">
                <?php foreach ($options as $opt) : ?>
                    <?php
                    $val = (string)($opt['value'] ?? '');
                    $text = (string)($opt['text'] ?? $val);
                    $selected = ($val === $current);
                    ?>
                    <option value="<?php esc_attr_e($val); ?>" <?= $selected ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($text); ?>
                </option>
                <?php endforeach; ?>
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
               value="<?php esc_attr_e($activeValue); ?>" size="<?= $size; ?>" class="form-control <?= $class; ?>">
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
               class="form-control"
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
        <input type="checkbox" id="jch-optimize_settings_<?= $settingName; ?>"
               class="m-0 p-2 form-check-input <?= $class; ?>"
               name="<?= "jch-optimize_settings[{$settingName}]"; ?>" data-toggle="toggle"
               data-onstyle="success" data-offstyle="danger"
               data-on="<?php _e('Yes', 'jch-optimize'); ?>"
               data-off="<?php _e('No', 'jch-optimize'); ?>" value="1" <?= $checked; ?>>
        <?php
    }

    public static function checkboxes(
        string $settingName,
        array $activeValues,
        array $options,
        string $class = ''
    ): void {
        $i = 0;
        $cols = count($options);
        if ($cols > 7) {
            $cols = ceil($cols / 2);
        }

        ?>
        <fieldset id="jch-optimize_settings_<?= $settingName; ?>" class="<?= $class; ?> float-start">
            <ul class="grid p-0" style="--bs-columns: <?= $cols ?>; --bs-gap: 0.3rem;">
                <?php
                foreach ($options as $key => $value) :
                    $checked = (in_array($key, $activeValues)) ? 'checked' : '';
                    ?>
                    <li class="g-col-1">
                        <input type="checkbox" id="jch-optimize_settings_<?= $settingName . $i; ?>"
                               class="mt-0 p-2 form-check-input"
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
        <textarea name="<?= "jch-optimize_settings[{$settingName}]"; ?>" cols="35" rows="3" class="form-control">
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

    public static function verifycftoken(string $settingName, $activeValue): void
    {
        $cfVerifyNonce = wp_create_nonce('jch_optimize_verify_cf_token')
        ?>
        <style>
            .cf-verify-wrap { /* already position-relative from markup */
            }

            .cf-float-alert {
                position: absolute;
                left: 0;
                right: 0;
                top: -0.5rem; /* sits just above the input */
                transform: translateY(-100%);
                z-index: 1060; /* above form controls; < modal (1050–1060) */
                pointer-events: none; /* don’t steal focus/mouse */
                width: 400px;
            }

            .cf-float-alert .alert {
                pointer-events: auto; /* allow dismiss button if you add one later */
                box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15);
            }

            .cf-verify-wrap .password-group {
                width: 75%;
            }
        </style>
        <div class="cf-verify-wrap d-flex align-items-start gap-2 position-relative">
            <?php self::text($settingName, $activeValue) ?>
            <!-- New verify button beside the field -->
            <button type="button" class="btn btn-outline-secondary btn-sm" id="cfVerifyBtn"
                    aria-label="Verify Cloudflare API token">
                Verify Token
            </button>

            <!-- Floating alert container (JS will fill it) -->
            <div class="cf-float-alert" aria-live="polite" aria-atomic="true"></div>
        </div>
        <script>
            (() => {
                const JchVerifyCfApi = () => {
                    window.JCH_CF_VERIFY_ENDPOINT = window.JCH_CF_VERIFY_ENDPOINT ||
                        ajaxurl + '?action=jch_cf_verify&_wpnonce=<?= $cfVerifyNonce ?>'

                    const btn = document.getElementById('cfVerifyBtn')            // existing button
                    const tokenEl = document.getElementById('jch-optimize_settings_cf_api_token')     // token input
                    const zoneEl = document.getElementById('jch-optimize_settings_cf_zone_id')       // zone input
                    const wrap = btn?.closest('.cf-verify-wrap')                   // from earlier markup
                    const alertHost = wrap?.querySelector('.cf-float-alert')
                    const badge = document.getElementById('cfZoneVerifiedBadge')    // optional badge near zone field

                    function showAlert (html, type = 'success', ttl = 4000) {
                        if (!alertHost) return
                        alertHost.innerHTML = ''
                        const el = document.createElement('div')
                        el.className = `alert alert-${type} fade show`
                        el.role = 'alert'
                        el.innerHTML = html
                        alertHost.appendChild(el)
                        setTimeout(() => {
                            el.classList.remove('show')
                            setTimeout(() => alertHost.innerHTML = '', 200)
                        }, ttl)
                    }

                    function setBusy (b, busy) {
                        if (!b) return
                        if (busy) {
                            b.disabled = true
                            b.dataset._orig = b.innerHTML
                            b.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                     <span class="ms-2">Verifying…</span>`
                        } else {
                            b.disabled = false
                            if (b.dataset._orig) b.innerHTML = b.dataset._orig
                        }
                    }

                    btn?.addEventListener('click', async () => {
                        const token = tokenEl?.value.trim() || ''
                        const zone = zoneEl?.value.trim() || ''

                        if (!token) {
                            showAlert('Please enter your Cloudflare API token.', 'warning')
                            return
                        }

                        // Joomla CSRF token name (J4/J5)
                        const csrfName = (window.Joomla && Joomla.getOptions)
                            ? (Joomla.getOptions('csrf.token') || '') : ''

                        setBusy(btn, true)
                        badge?.classList.add('d-none')

                        try {
                            const fd = new FormData()
                            fd.append('token', token)
                            if (zone) fd.append('zone_id', zone)
                            if (csrfName) fd.append(csrfName, '1')

                            const res = await fetch(window.JCH_CF_VERIFY_ENDPOINT, {
                                method: 'POST',
                                body: fd,
                                credentials: 'same-origin'
                            })
                            const json = await res.json().catch(() => ({}))

                            // Expect shape from PHP: { success, token_status, zone_read?, purge_ok?, verified_at? }
                            const ok = res.ok && (json.success === true || (json.data && json.data.success === true))
                            const data = (json.data && json.data.success !== undefined) ? json.data : json

                            if (ok) {
                                // Build a nice message
                                let msg = 'Token verified'
                                if (data.token_status) msg += ` (<strong>${data.token_status}</strong>)`
                                if (zone) {
                                    if (data.zone_read) msg += ', zone readable'
                                    if (data.purge_ok) msg += ', purge permission confirmed'
                                }
                                msg += '.'

                                showAlert(msg, 'success')

                                // Show "Verified" badge when zone was part of the check and both read+purge passed
                                if (zone && data.zone_read && data.purge_ok && badge) {
                                    badge.classList.remove('d-none')
                                    // Optional: show time since verification
                                    if (data.verified_at) {
                                        const dt = new Date(data.verified_at)
                                        if (!isNaN(+dt)) {
                                            const mins = Math.max(0, Math.round((Date.now() - dt.getTime()) / 60000))
                                            badge.textContent = mins < 1 ? 'Verified just now'
                                                : mins === 1 ? 'Verified 1 min ago'
                                                    : `Verified ${mins} mins ago`
                                        } else {
                                            badge.textContent = 'Verified'
                                        }
                                    } else {
                                        badge.textContent = 'Verified'
                                    }
                                }
                            } else {
                                const msg = json.message || (json.data && json.data.message) || 'Verification failed.'
                                showAlert(msg, 'danger')
                            }
                        } catch (err) {
                            showAlert('Network error. Please try again.', 'danger')
                        } finally {
                            setBusy(btn, false)
                        }
                    })
                }

                (document.readyState === 'loading')
                    ? document.addEventListener('DOMContentLoaded', JchVerifyCfApi, { once: true })
                    : JchVerifyCfApi()
            })()
        </script>
        <?php
    }
}
