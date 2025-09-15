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

function jchLazyLoadBgImages() {
    Object.values(jchLazyLoadSelectors).forEach(function (selector) {
        try {
            let elements = document.querySelectorAll(selector)

            elements.forEach((element) => {
                if (
                    element
                    && !element.classList.contains('jch-lazyload')
                    && !element.classList.contains('jch-lazyloaded')
                ) {
                    element.classList.add('jch-lazyload');
                }
            });
        } catch (e) {
            console.log(e.message);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener("DOMContentLoaded", jchLazyLoadBgImages);
} else {
    jchLazyLoadBgImages();
}

document.addEventListener("onJchDomLoaded", (event) => {
    jchLazyLoadBgImages();
    jchLazySizes.loader.checkElems();
});
