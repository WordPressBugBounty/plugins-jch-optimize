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
let jchPlatform = (function () {
    
    let jch_ajax_url_optimizeimages = ajaxurl + '?action=optimizeimages&_wpnonce=' + jch_optimize_image_url_nonce;
    let jch_ajax_url_multiselect = ajaxurl + '?action=multiselect&_wpnonce=' + jch_multiselect_url_nonce;
    
    let configure_url = ajaxurl + '?action=configuresettings';
    
    const formPrefix = 'jch-optimize_settings';
    
    const applyAutoSettings = function (int, id, nonce) {
        const auto_settings = document.querySelectorAll('li.dashicon-wrapper.auto-setting');
        const toggles = document.querySelectorAll('li.dashicon-wrapper.auto-setting .dashicon-toggle div.fa');
        let image = document.createElement('img');
        image.src = jch_loader_image_url;
        image.className = 'jch-pending-image';
        
        for (const toggle of toggles) {
            const parent = toggle.parentNode;
            parent.replaceChild(image.cloneNode(true), toggle);
        }
        
        let url = configure_url + '&task=applyautosetting&autosetting=s' + int + '&_ajax_nonce=' + nonce ;
        
        postData(url)
            .then(data => {
                for (const auto_setting of auto_settings) {
                    auto_setting.classList.remove('enabled');
                    auto_setting.classList.add('disabled');
                }
                
                    //Turn off all toggles
                    const pendingImages = document.querySelectorAll("li.dashicon-wrapper.auto-setting img.jch-pending-image");
                    let toggleDiv = document.createElement('div');
                    toggleDiv.className = "fs-6 fa fa-toggle-off";

                    for (const image of pendingImages) {
                        image.parentNode.replaceChild(toggleDiv.cloneNode(true), image);
                    }

                    //if the response returned without error then the setting is applied
                    if (data.success) {
                        const current_setting = document.getElementById(id);
                        current_setting.className = "dashicon-wrapper auto-setting enabled";
                        const activeToggle = current_setting.querySelector(".dashicon-toggle div.fa");
                        activeToggle.className = "fs-6 fa fa-toggle-on"

                        const combineFilesEnableInput = document.querySelector('.jch-dash-icons-switcher input');
                        combineFilesEnableInput.checked = true;
                        const combineFilesEnableLabel = document.querySelector('.jch-dash-icons-switcher label');
                        combineFilesEnableLabel.textContent = 'Enabled';
                    }

                })
                .catch(err => console.error(err))
    }
    
        const toggleSetting = function (setting, id, nonce) {
            let li = document.getElementById(id);
            let toggle = document.querySelector("#" + id + " div.dashicon-toggle div.fa");
            const image = document.createElement("img");
            image.src = jch_loader_image_url;
            image.className = 'jch-pending-image';

            const oldToggle = toggle.parentNode.replaceChild(image, toggle);

            let url = configure_url + '&task=togglesetting&setting=' + setting + '&_ajax_nonce=' + nonce;

            postData(url)
                .then(data => {
                    li.classList.remove("enabled", "disabled");
                    li.classList.add(data.class);

                    let toggleClass = data.class2 === 'enabled' ? 'fa-toggle-on' : 'fa-toggle-off';

                    if (id === 'optimize-css-delivery') {
                        let unused_css = document.getElementById("reduce-unused-css");
                        unused_css.classList.remove("enabled", "disabled");
                        unused_css.classList.add(data.class2);

                        let unusedCssToggle = unused_css.querySelector('.dashicon-toggle .fa');
                        unusedCssToggle.classList.remove('fa-toggle-on', 'fa-toggle-off');
                        unusedCssToggle.classList.add(toggleClass);
                    }

                    if (id === 'reduce-unused-css') {
                        let optimize_css = document.getElementById("optimize-css-delivery");
                        optimize_css.classList.remove("enabled", 'disabled');
                        optimize_css.classList.add(data.class2);

                        let optimizeCssToggle = optimize_css.querySelector('.dashicon-toggle .fa');
                        optimizeCssToggle.classList.remove('fa-toggle-on', 'fa-toggle-off');
                        optimizeCssToggle.classList.add(toggleClass);
                    }


                    if (setting === 'integrated_page_cache_enable') {
                        let mode_switcher_indicator = document.getElementById("mode-switcher-indicator");
                        if (mode_switcher_indicator !== null) {
                            mode_switcher_indicator.classList.remove(
                                "production",
                                "development",
                                "page-cache-only",
                                "page-cache-disabled"
                            );
                            mode_switcher_indicator.classList.add(data.status_class);
                        }

                        let page_cache_status = document.getElementById("page-cache-status");
                        if (page_cache_status !== null) {
                            page_cache_status.innerHTML = data.page_cache_status;
                        }
                    }

                    const pendingImage = li.querySelector('img.jch-pending-image');
                    oldToggle.className = 'fs-6 fa fa-toggle-' + (data.class === 'enabled' ? 'on' : 'off');
                    pendingImage.parentNode.replaceChild(oldToggle, pendingImage);
                })
                .catch(err => console.error(err));
        };

        const toggleCombineFilesEnable = function (nonce) {
            const checkboxParentDiv = document.getElementById('combine-files-enable');
            const autoSettings = document.querySelectorAll("li.dashicon-wrapper.auto-setting");

            for (const autoSetting of autoSettings) {
                const autoSettingToggle = autoSetting.querySelector('.dashicon-toggle div.fa');

                autoSetting.classList.remove('enabled');
                autoSetting.classList.add('disabled');

                autoSettingToggle.classList.remove('fa-toggle-on');
                autoSettingToggle.classList.add('fa-toggle-off');
            }

            postData(configure_url + '&task=togglesetting&setting=combine_files_enable&_ajax_nonce=' + nonce)
                .then(data => {
                    if (data.auto !== false) {
                        const enabled_auto_setting = document.getElementById(data.auto);
                        enabled_auto_setting.classList.remove("disabled");
                        enabled_auto_setting.classList.add("enabled");

                        const toggle = enabled_auto_setting.querySelector('.dashicon-toggle div.fa');
                        toggle.classList.remove('fa-toggle-off');
                        toggle.classList.add('fa-toggle-on');
                    }

                    const input = checkboxParentDiv.querySelector('input.form-check-input');
                    input.checked = data.class === 'enabled';
                    const label = checkboxParentDiv.querySelector('label.form-check-label');
                    label.textContent = data.class === 'enabled' ? 'Enabled' : 'Disabled';
                })
        }
    
    const submitForm = function () {
        document.getElementById('jch-optimize-settings-form').submit();
    }
    
    async function postData (url, data = {}) {
        const response = await fetch(url, {
            method: 'GET',
            cache: 'no-cache',
            mode: 'cors',
            headers: {
                'Content-Type': 'application/json'
            },
        })
        
        return response.json();
    }
    
    const getCacheInfo = function () {
        let url = ajaxurl + '?action=getcacheinfo';
        
        postData(url).then(data => {
            let numFiles = document.querySelectorAll('.numFiles-container');
            let fileSize = document.querySelectorAll('.fileSize-container');
            
            numFiles.forEach((container) => {
                container.innerHTML = data.numFiles;
            })
            
            fileSize.forEach((container) => {
                container.innerHTML = data.size;
            })
        })
    }
    
    return {
        //properties
        jch_ajax_url_optimizeimages: jch_ajax_url_optimizeimages,
        jch_ajax_url_multiselect: jch_ajax_url_multiselect,
        formPrefix: formPrefix,
        //methods
        applyAutoSettings: applyAutoSettings,
        toggleSetting: toggleSetting,
        submitForm: submitForm,
        getCacheInfo: getCacheInfo,
        toggleCombineFilesEnable:toggleCombineFilesEnable
    }
    
})()

window.jchPlatform = jchPlatform;