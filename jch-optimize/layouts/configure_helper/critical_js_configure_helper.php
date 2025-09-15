<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

/**
 * @var $loadingImageUrl
 * @var $baseUrl
 * @var $tableBodyAjaxUrl
 * @var $autoSaveAjaxUrl
 */
?>

<div class="modal fade" id="modalCriticaljsconfigurehelper" tabindex="-1"
 aria-labelledby="modalCriticaljsconfigurehelperLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCriticaljsconfigurehelperLabel">Critical JavaScript Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-5">
            <div class="input-group mb-3">
                <span class="input-group-text">URL</span>
                <input type="text" class="form-control" placeholder="<?= $baseUrl ?>" aria-label="Base url"
                aria-describedby="criticalJsBaseUrlButton" value="<?= $baseUrl ?>" id="criticalJsBaseUrlInput">
                <button class="btn btn-secondary" type="button" id="criticalJsBaseUrlButton">Reload</button>
            </div>
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th scope="col"><input type="checkbox" class="jchoptimize-criticalJs-checkall"></th>
                        <th scope="col">Check all</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                         <td colspan="3"><img src="<?= $loadingImageUrl ?>" alt="Body loading..."> </td>
                    </tr>
                </tbody>
            </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button id="modalCriticaljsSaveButton" type="button" class="btn btn-primary">Save changes</button>
            </div>
        </div>
    </div>
</div>
<script>
const criticalJsModal = document.getElementById('modalCriticaljsconfigurehelper');
const modalTableBody = criticalJsModal.querySelector('.modal-body tbody');
document.getElementById('criticalJsModalLaunchButton').addEventListener('click', () => {
    bootstrap.Modal.getOrCreateInstance(criticalJsModal).show();
});

criticalJsModal.addEventListener('hidden.bs.modal', () => {
    bootstrap.Modal.getInstance(criticalJsModal).dispose();
});

const criticalJsValues = {
    'pro_criticalJs': [],
    'pro_criticalScripts': [],
    'pro_criticalModules': [],
    'pro_criticalModulesScripts': [],
};

const configureHelperValues = {
    'criticalJs_configure_helper': [],
    'criticalScripts_configure_helper': [],
    'criticalModules_configure_helper': [],
    'criticalModulesScripts_configure_helper': []
};

document.addEventListener('JchOptimizeCriticalJsBodyLoaded', () => {
    const checkAllInput = document.querySelector('input.jchoptimize-criticalJs-checkall');
    const configHelperCheckboxes = document.querySelectorAll('input.jchoptimize-criticalJs-configure-helper');

    checkAllInput.checked = false;
    checkAllInput.onchange = (event) => {
        configHelperCheckboxes.forEach((checkbox) => {
            checkbox.checked = event.target.checked;
        })

        updateSettings();
    };

    configHelperCheckboxes.forEach((checkbox) => {
        checkbox.onchange = () => {
            updateSettings();
        }
    });

    const updateSettings = () => {
        for (const property in configureHelperValues) {
            if (configureHelperValues.hasOwnProperty(property)) {
                configureHelperValues[property] = [];
            }
        }

        configHelperCheckboxes.forEach((checkbox) => {
            const index = configureHelperValues[checkbox.name].indexOf(checkbox.value);

            if (checkbox.checked) {
                if (index < 0) {
                    configureHelperValues[checkbox.name].push(checkbox.value);
                }
            } else {
                if (index >= 0) {
                    configureHelperValues[checkbox.name].splice(index, 1);
                }
            }
        });

        saveSettingsAjaxly(configureHelperValues)
         .then(response => console.log(response))
         .catch(err => console.error(err));
    }

    updateSettings();

    criticalJsModal.addEventListener('hide.bs.modal', () => {
        checkAllInput.checked = false;
        configHelperCheckboxes.forEach((checkbox) => {
            checkbox.checked = false;
        })
        updateSettings();
    }, {
        once: true
    });

    document.getElementById('modalCriticaljsSaveButton').onclick = () => {
        for (const [inputId, value] of Object.entries(criticalJsValues)) {
            const criticalJsInput = document.querySelector('select[id$="' + inputId + '"]');
            if (criticalJsInput !== null) {
                Array.from(criticalJsInput.options).forEach((option) => {
                    if (option.selected) {
                        criticalJsValues[inputId].push(option.value);
                    }
                })
            }
        }

        const checkboxNameValueMap = new Map(
            Object.keys(configureHelperValues).map(
                (key, index) => [key, Object.keys(criticalJsValues)[index]]
            )
        )
        configHelperCheckboxes.forEach((checkbox) => {
            if (checkbox.checked) {
                for (const [checkboxName, checkboxValue] of checkboxNameValueMap.entries()) {
                     if (checkbox.name === checkboxName) {
                         criticalJsValues[checkboxValue].push(checkbox.value);
                         break;
                     }
                }
           }
        })

        saveSettingsAjaxly(criticalJsValues)
           .then(response => {
               console.log(response);
               bootstrap.Modal.getInstance(criticalJsModal).hide();
               location.reload();
           })
           .catch(err => console.error(err));
    };
});

function setBody() {
    const ajaxUrl = new URL('<?= $tableBodyAjaxUrl ?>', window.location.toString());
    const baseUrl = document.getElementById('criticalJsBaseUrlInput');
    ajaxUrl.searchParams.set('baseUrl', encodeURIComponent(baseUrl.value));
    fetch(ajaxUrl, {
        method: 'GET',
        headers: new Headers({
             'Content-Type': 'text/html',
             'User-Agent': 'JchOptimizeCrawler/<?= JCH_VERSION ?>'
        }),
        cache: 'no-store',
    })
    .then(response => response.text())
    .then(html => {
        modalTableBody.innerHTML = html;
        document.dispatchEvent(new Event('JchOptimizeCriticalJsBodyLoaded'));
    })
    .catch(err => console.error('Fetch error:', err));
}

async function saveSettingsAjaxly(values) {
    let ajaxUrl = new URL('<?= $autoSaveAjaxUrl ?>', window.location.toString());

    const response = await fetch(ajaxUrl, {
        method: 'POST',
        body: JSON.stringify(values),
        headers: new Headers({
            'Content-Type': 'application/json'
        }),
        cache: 'no-store'
    })

    if (!response.ok) {
        throw new Error(`Response status: ${response.status}`);
    }

    return response.json();
}

criticalJsModal.addEventListener('shown.bs.modal', () => {
    setBody();
});

document.getElementById('criticalJsBaseUrlButton').addEventListener('click', () => {
    modalTableBody.innerHTML = `<tr>
    <td colspan="3"><img src="<?= $loadingImageUrl ?>" alt="Body loading..."> </td>
    </tr>`;
    setBody();
});
</script>
