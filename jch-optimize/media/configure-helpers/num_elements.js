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

(function () {
    'use strict'

    const countElements = () => {
        let callback = (entries, observer) => {
            let numElementsIterated = 0;
            let numElementsAboveFold = 0;
            let fixedParents = [];

            entries.forEach((entry) => {
                let entryIsChildOfFixedParent = false;
                numElementsIterated++;

                fixedParents.forEach((parent) => {
                    if (parent.contains(entry.target)) {
                        entryIsChildOfFixedParent = true;
                    }
                });

                if (entryIsChildOfFixedParent) {
                    return;
                }

                if (entry.isIntersecting) {
                    if (window.getComputedStyle(entry.target).position === 'fixed'
                        || window.getComputedStyle(entry.target).position === 'absolute') {
                        fixedParents.push(entry.target);
                        return;
                    }

                    numElementsAboveFold = numElementsIterated;
                }
            })

            console.log(
                "%cNumber of elements above fold: '%c%i%c'",
                "padding: 5px 0; line-height: 1.2em;",
                "color: red;;",
                numElementsAboveFold,
                "color: black"
            );
            observer.disconnect();
        }

        const observer = new IntersectionObserver(callback);
        const targets = document.querySelectorAll('body, body *');

        targets.forEach((target) => {
            observer.observe(target);
        });
    }

    window.addEventListener('load', (e) => {
        countElements();
    })
}());
