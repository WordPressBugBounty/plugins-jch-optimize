/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2025 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

(() => {
    'use strict';

    // ---- platform config ----
    const resolveFormPrefix = () => {
        // 1) explicit JS config (best)
        if (window.jchPlatform && window.jchPlatform.formPrefix) return String(window.jchPlatform.formPrefix);

        // 2) Joomla: Script Options (J4/5)
        if (window.Joomla && Joomla.getOptions) {
            const opt = Joomla.getOptions('jch') || Joomla.getOptions('jchPlatform');
            if (opt && opt.formPrefix) return String(opt.formPrefix);
        }

        // 3) <body data-jch-form-prefix="..."> fallback
        const bodyAttr = document.body && document.body.getAttribute('data-jch-form-prefix');
        if (bodyAttr) return String(bodyAttr);

        // 4) default for Joomla
        return 'jform';
    }

    const FORM_PREFIX = resolveFormPrefix();

// Build an input name with the configured prefix
    const buildInputName = (param, idx, key) => `${FORM_PREFIX}[${param}][${idx}][${key}]`;

// Find a select by the configured prefix or by data-* fallbacks
    const findSelectByParam = (param) =>
        document.getElementById(`${FORM_PREFIX}_${param}`) ||
        q(`select.jch-multiselect[data-jch_param="${param}"]`) ||
        q(`select.jch-multiselect[data-param="${param}"]`);

    // ---------------- helpers ----------------
    const q = (sel, root = document) => root.querySelector(sel);
    const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    // shared state
    const instances = new Map();   // id -> Choices instance
    const optionCache = new Map();   // id -> [{value,label,customProperties?}]
    const selectedMap = new Map();   // id -> Map(value -> {label,rowId,index,extras})

    // ---- field helpers
    const getParam = (select) => {
        // If data attributes explicitly set, prefer them
        const attr = select.getAttribute('data-jch_param') ||
            select.getAttribute('data-param');
        if (attr) return attr;

        // Otherwise strip the configured prefix from the id
        const prefix = FORM_PREFIX + '_';
        return select.id.startsWith(prefix)
            ? select.id.slice(prefix.length)
            : select.id; // fallback
    };


    const isRowMode = (select) => !!document.getElementById('fieldset-' + getParam(select));

    const flashRow = (groupClass, fs) => {
        const root = fs || document;
        const cells = qa(`.${groupClass}`, root);
        if (!cells.length) return;

        cells.forEach(c => c.classList.add('jch-flash'));
        cells[0].scrollIntoView({block: 'nearest', behavior: 'smooth'});
        setTimeout(() => cells.forEach(c => c.classList.remove('jch-flash')), 1200);
    };

    const setChoicesFromCache = (id) => {
        const inst = instances.get(id);
        if (!inst) return;
        const list = optionCache.get(id) || [];
        inst.setChoices(list, 'value', 'label', true); // replace
    };

    // Read value type from the matching fieldset (row-mode fields only).
    const getValueType = (select) => {
        const fs = document.getElementById('fieldset-' + getParam(select));
        return fs ? fs.getAttribute('data-value-type') || null : null; // null when not set / not row-mode
    };

// --- truncation helpers (only for valueType='url') ---
    const getTruncateLength = (select) => {
        // You can override globally per-select via data-truncate-length="80" if desired
        const n = parseInt(select.getAttribute('data-truncate-length') || '65', 10);
        return Number.isFinite(n) ? n : 65;
    };

    const isAbsoluteUrl = (s) => /^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(s);

// Mirrors your PHP prepareFileForDisplay() for URLs
    const truncateUrlForDisplay = (value, select, length = getTruncateLength(select)) => {
        try {
            if (!length || typeof value !== 'string') return value;

            const url = new URL(value, window.location.origin);
            const crossOrigin = isAbsoluteUrl(value) && (url.origin !== window.location.origin);

            let preEps = '';
            let eps = '';
            let path = url.pathname || '';
            let budget = length;

            if (crossOrigin) {
                const portless = `${url.protocol}//${url.hostname}`;   // withPort(null)
                preEps = portless;
                budget = Math.max(0, budget - portless.length);
            }

            if (path.length > budget) {
                path = path.slice(-budget);
                preEps = preEps ? preEps + '/' : '';
                eps = '...';
            }

            const out = (preEps || eps) ? (preEps + eps + path) : (preEps + path);
            return out || value;
        } catch {
            // Fallback: tail truncate if it’s not a parseable URL
            const len = length || 65;
            return (typeof value === 'string' && value.length > len) ? ('...' + value.slice(-len)) : value;
        }
    };

// Only truncate when (a) this field’s valueType is 'url', and (b) the label equals the raw value.
    const displayLabel = (select, value, label) => {
        if (getValueType(select) !== 'url') {
            return label ?? value;
        }
        if (!label || label === value) {
            return truncateUrlForDisplay(value, select);
        }
        return label;
    };

    // Reliably find the Choices text input for this select
    const getChoicesWrapper = (select) => {
        // 1) most reliable: the closest ancestor with class "choices"
        if (select.closest) {
            const wrap = select.closest('.choices');
            if (wrap) return wrap;
        }
        // 2) fallback: parent’s parent (select is inside .choices__inner)
        const p = select.parentElement && select.parentElement.parentElement;
        if (p && p.classList && p.classList.contains('choices')) return p;
        // 3) last resort: nearest .choices inside the same container row
        const param = getParam(select);
        const row = document.getElementById('div-' + param) || select.parentElement;
        return row ? q('.choices', row) : null;
    };

    const getChoicesInput = (select) => {
        const wrap = getChoicesWrapper(select);
        return wrap ? q('.choices__input--cloned', wrap) : null;
    };

// Add a new value (typed or clicked Add) for both row-mode and normal fields
    const addNewItem = (select, rawVal) => {
        const val = (rawVal || '').trim();
        if (!val) return {ok: false, reason: 'empty'};

        const id = select.id;
        const rowMode = isRowMode(select);

        if (rowMode) {
            // already added as a row?
            const selMap = selectedMap.get(id);
            if (selMap && selMap.has(val)) {
                const {rowId} = selMap.get(val);
                flashRow(rowId, document.getElementById('fieldset-' + getParam(select)));
                resetFilter(select);
                return {ok: false, reason: 'duplicate-selected'};
            }

            // if the value is in dropdown cache, remove it (like picking from dropdown)
            const cache = optionCache.get(id) || [];
            const idx = cache.findIndex(c => c.value === val);
            if (idx > -1) {
                cache.splice(idx, 1);
                optionCache.set(id, cache);
                setChoicesFromCache(id);
            }

            // add as a new row
            addRow({select, value: val, label: displayLabel(select, val, val), subConfigs: getSubConfigs(select)});
            resetFilter(select);
            return {ok: true, reason: (idx > -1 ? 'selected-existing' : 'added-new')};
        }

        // ---- normal (non row-mode) fields ----
        // if already selected, just inform
        const alreadySelected = Array.from(select.selectedOptions || []).some(o => o.value === val);
        if (alreadySelected) {
            resetFilter(select);
            return {ok: false, reason: 'duplicate-selected'};
        }

        const list = optionCache.get(id) || [];
        const existsInDropdown = list.some(c => c.value === val);

        if (!existsInDropdown) {
            list.push({value: val, label: displayLabel(select, val, val)});
            optionCache.set(id, list);
            setChoicesFromCache(id);
        }

        // select it
        try {
            instances.get(id).setChoiceByValue(val);
        } catch (_) {
        }
        resetFilter(select);
        return {ok: true, reason: existsInDropdown ? 'selected-existing' : 'added-new'};
    };

    // Clear the current search text, re-run filtering, and refresh Choices list
    // Clear the current search text, refresh results, and close the dropdown.
    const resetFilter = (select) => {
        const id = select.id;
        const inst = instances.get(id);
        const wrap = getChoicesWrapper(select);
        const input = wrap ? q('.choices__input--cloned', wrap) : null;

        if (input) {
            input.value = '';
            // trigger Choices' internal filtering to rebuild the list
            input.dispatchEvent(new Event('input', {bubbles: true}));
        }
        if (inst) {
            try {
                inst.hideDropdown();
            } catch (_) {
            }
        }
        // also strip 'is-open' class in case Choices left it behind
        if (wrap) wrap.classList.remove('is-open');
    };

    // ---- subfield config
    const getSubConfigs = (select) => {
        const ds = select.getAttribute('data-subfields');
        if (ds) {
            try {
                return JSON.parse(ds);
            } catch (e) {
            }
        }
        const param = getParam(select);
        const cfgEl = document.getElementById('subfields-' + param);
        if (cfgEl && cfgEl.textContent.trim()) {
            try {
                return JSON.parse(cfgEl.textContent);
            } catch (e) {
            }
        }
        // default for classic JS-excludes if nothing provided
        return [{type: 'checkbox', name: 'ieo'}, {type: 'checkbox', name: 'dontmove'}];
    };

    const renderSubfield = (cfg, param, idx) => {
        const name = buildInputName(param, idx, cfg.name);
        const cls = cfg.class || '';
        if (cfg.type === 'checkbox') {
            const checked = (cfg.defaultValue || cfg.checked) ? ' checked' : '';
            return `<input type="checkbox" class="${cls} subfield" name="${name}"${checked}>`;
        }
        if (cfg.type === 'select') {
            const opts = (cfg.options || cfg.htmlOptions || []).map(o => {
                const sel = (o.selected === true || o.selected === 'selected' ||
                    (cfg.defaultValue != null && cfg.defaultValue === o.value)) ? ' selected' : '';
                const text = (o.text != null ? o.text : (o.label != null ? o.label : o.value));
                return `<option value="${o.value}"${sel}>${text}</option>`;
            }).join('');
            return `<select name="${name}" class="${cls} subfield">${opts}</select>`;
        }
        // text (default)
        const val = (cfg.defaultValue != null ? cfg.defaultValue : '');
        return `<input type="text" class="${cls} subfield" name="${name}" value="${val}">`;
    };

    // ---- layout helper (keep input + add button on one row)
    const ensureRowLayout = (select) => {
        const param = getParam(select);
        const holder = document.getElementById('div-' + param) || select.parentElement;
        if (!holder) return;
        holder.classList.add('jch-multiselect-row');
        // inline fallback if no CSS present
        const cs = window.getComputedStyle(holder);
        if (cs.display !== 'flex') {
            holder.style.display = 'flex';
            holder.style.alignItems = 'center';
            holder.style.gap = '.5rem';
        }
    };

    // ---------------- rows ----------------
    const addRow = ({select, value, label, subConfigs}) => {
        const id = select.id;
        const param = getParam(select);
        const fs = document.getElementById('fieldset-' + param);
        if (!fs) return;

        if (!selectedMap.has(id)) selectedMap.set(id, new Map());
        const selMap = selectedMap.get(id);

        // Next row index (your existing scheme)
        const idx = Number(fs.getAttribute('data-index') || '0');
        const valueType = fs.getAttribute('data-value-type') || 'url';
        fs.setAttribute('data-index', String(idx + 1));

        const groupClass = `group${idx}`;

        // ---- 1) URL / "pill" cell ----
        const name = buildInputName(param, idx, valueType);

        const excludesCell = document.createElement('span');
        excludesCell.className = `${groupClass} jch-ms-excludes jch-ms-cell`;
        excludesCell.innerHTML = `
    <span>
      <input type="text" readonly size="${Math.max(11, (value.length / 2) | 0)}"
             value="${value}" name="${name}">
      ${displayLabel(select, value, label)}
      <button type="button" class="jch-multiselect-remove-button" aria-label="Remove">Remove</button>
    </span>
  `;

        // Append after headers (but appending at the end is fine as long as headers are first)
        fs.appendChild(excludesCell);

        // ---- 2) Subfield cells ----
        // We render each cfg inside a "cell" span so your CSS can border/pad consistently.
        const extras = {};

        (subConfigs || []).forEach((cfg) => {
            const cell = document.createElement('span');

            // These mirror your existing semantics, but now as flat grid cells.
            // You can include cfg.name-based class if you still want it for styling:
            // e.g. "jch-ms-anonymous" "jch-ms-use-credentials"
            const nameClass = cfg.name ? `jch-ms-${cfg.name}` : '';
            const typeClass = 'has-' + cfg.type;

            cell.className = `${groupClass} ${nameClass} jch-ms-cell has-subfield ${typeClass}`.trim();

            // Render the actual control
            cell.innerHTML = renderSubfield(cfg, param, idx);

            fs.appendChild(cell);

            // Track extras + listeners in selectedMap (like before)
            const selector = (cfg.type === 'select') ? 'select' : 'input';
            const inputName = buildInputName(param, idx, cfg.name);
            const el = q(`${selector}[name="${inputName}"]`, cell);

            extras[cfg.name] =
                cfg.type === 'checkbox' ? !!(el && el.checked) :
                    (el ? el.value : '');

            if (el) {
                el.addEventListener('change', () => {
                    const rec = selMap.get(value);
                    if (!rec) return;
                    rec.extras[cfg.name] = (cfg.type === 'checkbox') ? el.checked : el.value;
                });
            }
        });

        // ---- 3) record selection ----
        // rowId is now the groupClass since there is no wrapper element
        selMap.set(value, {
            label,
            rowId: groupClass,
            index: idx,
            extras
        });
    };


    // delegated remove (works for PHP-prebuilt rows too)
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.jch-multiselect-remove-button');
        if (!btn) return;

        // In the grid layout, the button lives inside the "excludes" cell
        const cell = btn.closest('.jch-ms-cell.jch-ms-excludes');
        const fs = cell && cell.closest('fieldset[id^="fieldset-"]');
        if (!cell || !fs) return;

        // Extract the group class, e.g. "group2"
        const groupClass = Array.from(cell.classList).find(c => /^group\d+$/.test(c));
        if (!groupClass) {
            // fallback: just remove this cell
            cell.remove();
            return;
        }

        const param = fs.id.replace(/^fieldset-/, '');
        const select = findSelectByParam(param);

        // Read the value from the hidden readonly input (still in the excludes cell)
        const valueEl = q('input[readonly]', cell);
        const value = valueEl ? valueEl.value : null;

        // 1) Remove all cells belonging to this row
        //    (only within this fieldset so we don't touch other fields)
        const rowCells = qa(`.${groupClass}`, fs);
        rowCells.forEach(el => el.remove());

        // If we can't reconcile with Choices, we're done.
        if (!select || !value) return;

        const id = select.id;

        // 2) Return to dropdown if missing
        const list = optionCache.get(id) || [];
        if (!list.some(c => c.value === value)) {
            list.push({value, label: displayLabel(select, value, value)});
            optionCache.set(id, list);
        }
        setChoicesFromCache(id);

        // 3) Remove active item from Choices (in case it exists for non-row-mode fields)
        const inst = instances.get(id);
        if (inst) {
            try {
                inst.removeActiveItemsByValue(value);
            } catch (_) {
            }
        }

        // 4) Update internal selected map
        const selMap = selectedMap.get(id);
        if (selMap) selMap.delete(value);

        // if there are no remaining rows, reset index
        fs.setAttribute('data-index', String(qa('.jch-ms-excludes.jch-ms-cell', fs).length));
    });

    // hydrate PHP-prebuilt rows -> keep internal state & remove duplicates from dropdown
    const hydrateExistingRows = () => {
        qa('fieldset[id^="fieldset-"]').forEach((fs) => {
            const param = fs.id.replace(/^fieldset-/, '');
            const select = findSelectByParam(param);
            if (!select) return;

            const id = select.id;
            if (!selectedMap.has(id)) selectedMap.set(id, new Map());
            const selMap = selectedMap.get(id);

            // Each row is identified by its excludes cell
            const excludesCells = qa('.jch-ms-excludes.jch-ms-cell', fs);

            excludesCells.forEach((exCell) => {
                const groupClass = Array.from(exCell.classList).find(c => /^group\d+$/.test(c));
                if (!groupClass) return;

                const valueEl = q('input[readonly]', exCell);
                const value = valueEl ? valueEl.value : null;
                if (!value) return;

                // Gather extras from all inputs/selects in this group
                const extras = {};
                qa(`.${groupClass} input, .${groupClass} select, .${groupClass} textarea`, fs).forEach((inp) => {
                    const m = inp.name && inp.name.match(/\[(\w+)\]$/);
                    if (!m) return;
                    const key = m[1];
                    extras[key] = (inp.type === 'checkbox') ? inp.checked : inp.value;
                });

                // Record in state
                const idxNum = parseInt(groupClass.replace('group', ''), 10);
                selMap.set(value, {
                    label: value,
                    rowId: groupClass,
                    index: Number.isFinite(idxNum) ? idxNum : 0,
                    extras
                });

                // Ensure dropdown doesn't still offer this value
                const list = optionCache.get(id);
                if (list) {
                    optionCache.set(id, list.filter(c => c.value !== value));
                    setChoicesFromCache(id);
                }

                resetFilter(select);
            });
        });
    };

    const closeAllChoices = () => {
        qa('select.jch-multiselect').forEach((sel) => {
            const inst = instances.get(sel.id);
            if (inst) {
                try {
                    inst.hideDropdown();
                } catch (_) {
                }
            }
            const wrap = getChoicesWrapper(sel);
            if (wrap) {
                wrap.classList.remove('is-open');
                const input = q('.choices__input--cloned', wrap);
                if (input && document.activeElement === input) input.blur();
            }
        });
    };

    // ---------------- field setup ----------------
    const setupField = (select) => {
        const id = select.id;
        const param = getParam(select);

        ensureRowLayout(select);

        if (!selectedMap.has(id)) selectedMap.set(id, new Map());

        // precompute row-mode & config
        const rowMode = isRowMode(select);
        const subConfigs = rowMode ? getSubConfigs(select) : null;

        // Choices
        const inst = new Choices(select, {
            removeItemButton: !!select.multiple,
            searchEnabled: true,
            shouldSort: false,
            duplicateItemsAllowed: false,
            addItems: true,

            // UX: hints
            searchPlaceholderValue: rowMode
                ? 'Type or paste… press Enter (or click Add) to add'
                : 'Type to search… press Enter to add',
            noResultsText: 'No matches. Press Enter or click “Add item” to create it.',
            noChoicesText: 'No options yet. Type and press Enter to add.',
            itemSelectText: 'Press to select',
            addItemText: (val) => `Add "${val}"`
        });
        instances.set(id, inst);

        // Enter-to-add: if no real highlighted choice, create a new item
        const inputEl = getChoicesInput(select);
        if (inputEl) {
            inputEl.addEventListener('keydown', (ev) => {
                if (ev.key !== 'Enter') return;

                const wrap = getChoicesWrapper(select);
                const highlighted = wrap && q(
                    '.choices__list--dropdown .choices__item--choice.is-highlighted:not(.has-no-choices)',
                    wrap
                );

                // If a real option is highlighted, let Choices handle selection
                if (highlighted) return;

                // Otherwise, add the typed value
                const res = addNewItem(select, inputEl.value);
                if (res.ok) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    inputEl.value = '';
                } else if (res.reason === 'duplicate-selected') {
                    // Highlight existing selection instead of silently doing nothing
                    ev.preventDefault();
                    ev.stopPropagation();
                }
            });
        }

        // selecting a choice
        select.addEventListener('addItem', (e) => {
            const value = e.detail && e.detail.value;
            if (value == null) return;

            const cache = optionCache.get(id) || [];
            const found = cache.find(c => c.value === value);
            const label = (found && found.label) || value;

            if (rowMode) {
                // avoid dup rows
                if (selectedMap.get(id).has(value)) {
                    try {
                        inst.removeActiveItemsByValue(value);
                    } catch (_) {
                    }
                    return;
                }
                const shown = displayLabel(select, value, label);
                addRow({select, value, label: shown, subConfigs});

                // visually clear & remove from dropdown list
                try {
                    inst.removeActiveItemsByValue(value);
                } catch (_) {
                }
                if (found) {
                    optionCache.set(id, cache.filter(c => c.value !== value));
                    setChoicesFromCache(id);
                }
            } else {
                // normal field: keep selection; do not remove from choices
            }
        });

        // Add item button
        const holder = document.getElementById('div-' + param) || select.parentElement;
        const addBtn = holder && holder.querySelector('.jch-multiselect-add-button');
        if (addBtn) {
            addBtn.addEventListener('click', () => {
                const input = getChoicesInput(select);
                const typed = input?.value || '';

                const res = addNewItem(select, typed);

                if (!res.ok) {
                    if (res.reason === 'empty') {
                        alert('Please type something to add.');
                    } else if (res.reason === 'duplicate-selected') {
                        // optional friendlier UX than alert:
                        console.warn('That item is already added.');
                    }
                } else {
                    // success: clear the box
                    if (input) input.value = '';
                }

            });
        }
    };

    // ---------------- batch load ----------------
    const batchLoad = (selects) => {
        const items = selects.map(el => ({
            id: el.id,
            param: getParam(el),
            type: el.getAttribute('data-jch_type') || el.getAttribute('data-type') || '',
            group: el.getAttribute('data-jch_group') || el.getAttribute('data-group') || ''
        }));

        const endpoint =
            (typeof jchPlatform !== 'undefined' && jchPlatform.jch_ajax_url_multiselect) ||
            (selects[0] && selects[0].getAttribute('data-endpoint')) || null;
        if (!endpoint) return;

        const body = new URLSearchParams();
        body.append('data', JSON.stringify(items));
        const tokenName = (window.Joomla && Joomla.getOptions && Joomla.getOptions('csrf.token')) || null;
        if (tokenName) body.append(tokenName, '1');

        fetch(endpoint + '&_=' + Date.now(), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: body.toString()
        })
            .then(r => r.json())
            .then(resp => {
                // Your shape: { success, message, code, data: { <id>: { success, message, code, data } } }
                const bag = resp && resp.data;
                if (!bag || typeof bag !== 'object') return;

                Object.keys(bag).forEach(id => {
                    const entry = bag[id];
                    if (!entry || typeof entry !== 'object') return;

                    const inner = entry.data;
                    let list = [];
                    if (inner && typeof inner === 'object' && !Array.isArray(inner)) {
                        // map of value->label
                        list = Object.keys(inner).map(v => ({value: v, label: inner[v]}));
                    } else if (Array.isArray(inner)) {
                        list = []; // empty (or pre-shaped array in other APIs)
                    }

                    // normalize to Choices-friendly items
                    list = list.map(c => ({
                        value: c.value,
                        label: (c.label != null ? c.label : c.value),
                        customProperties: (c.customProperties || null)
                    }));

                    optionCache.set(id, list);

                    const selectEl = document.getElementById(id);
                    if (selectEl) {
                        setChoicesFromCache(id);

                        const param = getParam(selectEl);
                        const img = document.getElementById('img-' + param);
                        if (img) img.remove();
                        const btn = q('#div-' + param + ' .jch-multiselect-add-button');
                        if (btn) btn.style.display = '';
                    }
                });

                // pick up PHP-prebuilt rows and de-dupe dropdown lists
                hydrateExistingRows();
                closeAllChoices();
            })
            .catch(err => {
                console.error('multiselect batch error:', err);
                // avoid stuck loaders if something fails
                qa('img.jch-multiselect-loading-image').forEach(img => img.remove());
                qa('.jch-multiselect-add-button').forEach(btn => btn.style.display = '');
            });
    };

    // ---------------- boot ----------------
    window.addEventListener('pageshow', () => closeAllChoices());

    function init() {
        const selects = qa('select.jch-multiselect');
        selects.forEach(setupField);
        batchLoad(selects);
    }

    (document.readyState === 'loading')
        ? document.addEventListener('DOMContentLoaded', init, {once: true})
        : init();
})();
