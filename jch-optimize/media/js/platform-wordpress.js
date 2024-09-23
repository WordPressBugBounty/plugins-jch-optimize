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
const jchPlatform = (function () {
    
    let jch_ajax_url_optimizeimages = ajaxurl + '?action=optimizeimages&_wpnonce=' + jch_optimize_image_url_nonce;
    let jch_ajax_url_smartcombine = ajaxurl + '?action=smartcombine';
    let jch_ajax_url_multiselect = ajaxurl + '?action=multiselect&_wpnonce=' + jch_multiselect_url_nonce;
    
    let configure_url = ajaxurl + '?action=configuresettings';
    
    const setting_prefix = 'jch-optimize_settings';
    
    const applyAutoSettings = function (int, id, nonce) {
        const auto_settings = document.querySelectorAll('figure.icon.auto-setting');
        const wrappers = document.querySelectorAll('figure.icon.auto-setting span.toggle-wrapper');
        let image = document.createElement('img');
        image.src = jch_loader_image_url;
        
        for (const wrapper of wrappers) {
            wrapper.replaceChild(image.cloneNode(true), wrapper.firstChild);
        }
        
        let url = configure_url + '&task=applyautosetting&autosetting=s' + int + '&_ajax_nonce=' + nonce ;
        
        postData(url)
            .then(data => {
                for (const auto_setting of auto_settings) {
                    auto_setting.className = 'icon auto-setting disabled';
                }
                
                //if the response returned without error then the setting is applied
                if (data.success) {
                    const current_setting = document.getElementById(id);
                    current_setting.className = 'icon auto-setting enabled';
                    const enable_combine = document.getElementById('combine-files-enable');
                    enable_combine.className = 'icon enabled';
                }
                
                for (const wrapper of wrappers) {
                    let toggle = document.createElement('i');
                    toggle.className = 'toggle fa';
                    wrapper.replaceChild(toggle, wrapper.firstChild);
                }
            })
    }
    
    const toggleSetting = function (setting, id, nonce) {
        let figure = document.getElementById(id);
        let wrapper = document.querySelector('#' + id + ' span.toggle-wrapper');
        let toggle = wrapper.firstChild;
        const image = document.createElement('img');
        image.src = jch_loader_image_url;
        wrapper.replaceChild(image, toggle);
        
        if (setting === 'combine_files_enable') {
            const auto_settings = document.querySelectorAll('figure.icon.auto-setting');
            for (const auto_setting of auto_settings) {
                auto_setting.className = 'icon auto-setting disabled';
            }
        }
        
        let url = configure_url + '&task=togglesetting&setting=' + setting + '&_ajax_nonce=' + nonce;
        
        postData(url)
            .then(data => {
                figure.classList.remove('enabled', 'disabled');
                figure.classList.add(data.class);
                
                if (id === 'optimize-css-delivery') {
                    let unused_css = document.getElementById('reduce-unused-css');
                    unused_css.classList.remove('enabled', 'disabled');
                    unused_css.classList.add(data.class2);
                }
                
                if (id === 'reduce-unused-css') {
                    let optimize_css = document.getElementById('optimize-css-delivery');
                    optimize_css.classList.remove('enabled', 'disabled');
                    optimize_css.classList.add(data.class2);
                }
                
                let enabled_auto_setting
                if (setting === 'combine_files_enable') {
                    if (data.auto !== false) {
                        enabled_auto_setting = document.getElementById(data.auto);
                        enabled_auto_setting.classList.remove('disabled');
                        enabled_auto_setting.classList.add('enabled');
                    }
                }
                wrapper.replaceChild(toggle, image);
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
        jch_ajax_url_smartcombine: jch_ajax_url_smartcombine,
        jch_ajax_url_multiselect: jch_ajax_url_multiselect,
        setting_prefix: setting_prefix,
        //methods
        applyAutoSettings: applyAutoSettings,
        toggleSetting: toggleSetting,
        submitForm: submitForm,
        getCacheInfo: getCacheInfo
    }
    
})()
