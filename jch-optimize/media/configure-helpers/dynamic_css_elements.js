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

    let dynamicSelectors = [];

    const getNewSelectorsFromChangedAttributes = (mutationRecord) => {
        let prefix, newValue;

        if (mutationRecord.attributeName === 'id') {
            prefix = '#';
            newValue = mutationRecord.target.id;
        }

        if (mutationRecord.attributeName === 'class') {
            prefix = '.';
            newValue = mutationRecord.target.className;
        }

        if (mutationRecord.oldValue) {
            newValue = newValue.substring(mutationRecord.oldValue.length);
            newValue.trim().split(' ')
                .filter((a) => a.length > 0)
                .map((a) => prefix + a)
                .forEach((a) => {
                    addToDynamicSelectorsArray(a)
                });
        }
    }

    const getNewSelectorsFromAddedChildren = (mutationRecord) => {
        mutationRecord.addedNodes.forEach((node) => {
            if (node.id !== undefined && node.id.length > 0) {
                addToDynamicSelectorsArray('#' + node.id);
            }

            if (node.classList !== undefined) {
                node.classList.forEach((className) => {
                    addToDynamicSelectorsArray('.' + className);
                })
            }
        })
    }

    const addToDynamicSelectorsArray = (item) => {
        if (!dynamicSelectors.includes(item)) {
            dynamicSelectors.push(item);
        }
    }

    const mutationObserverCallback = (mutationList, observer) => {
        mutationList.forEach((mutationRecord) => {
            if (mutationRecord.type === 'attributes') {
                getNewSelectorsFromChangedAttributes(mutationRecord);
            }

            if (mutationRecord.type === 'childList') {
                getNewSelectorsFromAddedChildren(mutationRecord);
            }
        });
    }

    const mutationObserver = new MutationObserver(mutationObserverCallback);
    mutationObserver.observe(
        document.querySelector('body'),
        {
            subtree: true,
            childList: true,
            attributeFilter: ['id', 'class'],
            attributeOldValue: true
        }
    );

    window.addEventListener('load', (e) => {
        mutationObserver.disconnect();

        if (dynamicSelectors.length > 0) {
            dynamicSelectors = dynamicSelectors.map((a) => {
                return {'CSS Dynamic Selectors': a}
            });

            console.table(dynamicSelectors);
        } else {
            console.log('No Dynamic CSS Selectors detected.')
        }
    })
}())
