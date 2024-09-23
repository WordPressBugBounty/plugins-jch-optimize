/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/joomla-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2021 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

/**
 * This file is a loose 'interface' template to be used by the various platform projects to hook platform specific
 * codes into the core files, so they can be re-used easily
 */

const jchPlatform = (function ($) {

    let jch_ajax_url_optimizeimages = ''
    let jch_ajax_url_multiselect = ''
    let jch_ajax_url_smartcombine = ''

    /**
     * @param int
     */
    const applyAutoSettings = function (int) {
    };

    /**
     * @param setting
     */
    const toggleSetting = function (setting) {
    };

    const submitForm = function () {

    };

    return {
        jch_ajax_url_multiselect: jch_ajax_url_multiselect,
        jch_ajax_url_optimizeimages: jch_ajax_url_optimizeimages,
        jch_ajax_url_smartcombine: jch_ajax_url_smartcombine,
        applyAutoSettings: applyAutoSettings,
        toggleSetting: toggleSetting,
        submitForm: submitForm
    }
})(jQuery);
