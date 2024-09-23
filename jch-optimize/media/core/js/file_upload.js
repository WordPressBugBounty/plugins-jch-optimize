/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

function getSettingsFileUpload()
{
    const fileInput = document.getElementById('bulk-settings-file-input');
    const form = document.forms.namedItem('bulk-settings-form');

    fileInput.addEventListener('change', function () {
        if (fileInput.files.length) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'task';
            hiddenInput.value = 'importsettings';

            form.appendChild(hiddenInput);

            form.submit();
        }
    });

    fileInput.click();
}

function loadBulkSettingsModal(){
    let modalLoaded = true;

    try{
        const modal = new bootstrap.Modal('#bulk-settings-modal-container', {
            backdrop: 'static',
            keyboard: false
        })
        modal.show();
    } catch (e) {
        modalLoaded = false;
    }

    if(!modalLoaded){
        //Try with jQuery for joomla3
        jQuery('#bulk-settings-modal-container').modal({
            backdrop: 'static',
            keyboard: false,
            show: true
        });
    }
};