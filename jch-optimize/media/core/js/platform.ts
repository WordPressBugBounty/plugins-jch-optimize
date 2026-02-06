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

interface JchPlatform {
    jch_ajax_url_multiselect: string;
    jch_ajax_url_optimize_images: string;

    applyAutoSettings(setting: number): void;
    toggleSetting(setting: string): void;
    submitForm(): void;
}
