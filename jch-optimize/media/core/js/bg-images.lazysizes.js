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

function jchLazyLoadBgImages(root = document) {

    const valid = [];

    Object.values(window.jchLazyLoadSelectors).forEach((s) => {
        try {
            document.createDocumentFragment().querySelector(s);
            valid.push(s);
        } catch (e) {
            console.warn('Invalid selector skipped:', s);
        }
    });

    // root can be the document or a specific DOM subtree (lazy-loaded block)
    try {
        const elements = root.querySelectorAll(valid.join(','));

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
        console.warn('jchLazyLoadBgImages:', e.message);
    }
}

// Initial run on full document
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => jchLazyLoadBgImages(document));
} else {
    jchLazyLoadBgImages(document);
}

// Respond to lazy DOM block loads
document.addEventListener('jch:domBlockLoaded', (event) => {
    const target = event.detail && event.detail.target ? event.detail.target : document;

    // Process only inside the new block (or entire doc if no target provided)
    jchLazyLoadBgImages(target);

    // Run LazySizes refresh. Some versions of LazySizes accept a subtree arg.
    if (window.jchLazySizes && window.jchLazySizes.loader) {
        const loader = window.jchLazySizes.loader;
        if (typeof loader.checkElems === 'function') {
            // Some LazySizes builds support a parameter (root) to limit scanning;
            loader.checkElems(target);
        } else if (typeof window.lazySizes !== 'undefined' && window.lazySizes.autoSizer) {
            // fallback if using plain lazySizes
            window.lazySizes.autoSizer.checkElems(target);
        }
    }
});
