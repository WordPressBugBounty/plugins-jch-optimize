/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2021 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

(function ($) {
    $(document).ready(function () {
        new ResizeSensor($('[class^="g-col"]'), function () {
            $('[class^="g-col"]').each(function () {
                let rows = Math.round($(this).find('.admin-panel-block').height() / 30)
                $(this).css('grid-row-end', 'span ' + rows)
            })
        })
    })
})(jQuery)