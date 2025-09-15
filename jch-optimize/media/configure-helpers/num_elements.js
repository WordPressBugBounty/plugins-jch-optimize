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
    const DEFAULT_IGNORED_TAGS = ['SCRIPT', 'STYLE', 'TEMPLATE', 'META', 'HEAD', 'NOSCRIPT', 'LINK'];
    let foldBreakingElements = [];

    function isOutOfFlow(el) {
        const pos = getComputedStyle(el).position;
        return pos === 'absolute' || pos === 'fixed';
    }

    function isBelowFold(el) {
        const rect = el.getBoundingClientRect();
        return rect.top >= window.innerHeight;
    }

    function isAboveFold(el) {
        const rect = el.getBoundingClientRect();
        return rect.top < window.innerHeight;
    }

    function getInitialElements(ignoreTags = false) {
        const all = [];
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_ELEMENT, {
            acceptNode: node =>
                ignoreTags && DEFAULT_IGNORED_TAGS.includes(node.tagName)
                    ? NodeFilter.FILTER_REJECT
                    : NodeFilter.FILTER_ACCEPT
        });
        let node;
        while ((node = walker.nextNode())) {
            all.push(node);
        }
        return all;
    }

    function countAboveFoldElements({numberOfFoldBreaks = 1, ignoreTags = false} = {}) {
        if (window.scrollY !== 0) {
            console.warn('Scroll to top to run count of elements above fold.');
            return;
        }

        const elements = getInitialElements(ignoreTags);
        let count = 0;
        foldBreakingElements = [];
        let breaksFound = 0;
        let currentlyTraversingBelowFold = false;

        for (const el of elements) {
            const outOfFlow = isOutOfFlow(el);
            const belowFold = isBelowFold(el);

            if (!outOfFlow) {
                if (!currentlyTraversingBelowFold && belowFold) {
                    // first fold break
                    foldBreakingElements.push(el);
                    breaksFound++;
                    currentlyTraversingBelowFold = true;
                } else if (currentlyTraversingBelowFold && isAboveFold(el)) {
                    // resumed above fold
                    currentlyTraversingBelowFold = false;
                }

            }

            count++;

            if (breaksFound >= numberOfFoldBreaks) break;
        }

        console.log("Number of elements above fold:", count);
    }

    function highlightFoldBreakers(index = 0) {
        if (foldBreakingElements.length === 0) {
            console.info('No fold-breaking elements found. Run countAboveFoldElements() first.');
            return;
        }

        const el = foldBreakingElements[index];
        if (!el) {
            console.warn(`No fold-breaker at index ${index}`);
            return;
        }

        el.style.outline = '3px solid red';
        el.scrollIntoView({behavior: 'smooth', block: 'center'});
        console.warn(`Fold-breaking element [${index}]:`, el);
    }

    function removeOverlay() {
        document.getElementById('element-highlight-line')?.remove();
        document.getElementById('element-highlight-marker')?.remove();
        document.getElementById('element-highlight-container')?.remove();
    }

    function highlightElementWithOverlay(selector = '#jchoptimize-elements-marker', options = {}) {
        const element = document.querySelector(selector);
        if (!element) {
            console.warn(`Element not found for selector: ${selector}`);
            return;
        }

        // Find nearest visible ancestor
        let target = element;
        while (target && !isVisible(target)) {
            target = target.parentElement;
        }

        if (!target) {
            console.warn("No visible ancestor found for the element.");
            return;
        }

        // Get document-relative position
        const rect = target.getBoundingClientRect();
        const scrollTop = window.scrollY || document.documentElement.scrollTop;
        const scrollLeft = window.scrollX || document.documentElement.scrollLeft;
        const top = rect.top + scrollTop;
        const left = rect.left + scrollLeft;

        // Create container for overlay elements if needed
        let overlayContainer = document.getElementById("element-highlight-container");
        if (!overlayContainer) {
            overlayContainer = document.createElement("div");
            overlayContainer.id = "element-highlight-container";
            document.body.appendChild(overlayContainer);
            Object.assign(overlayContainer.style, {
                position: "absolute",
                top: "0",
                left: "0",
                width: "100%",
                height: "0", // just a container
                pointerEvents: "none",
                zIndex: 9999,
            });
        }

        // Create or update the horizontal line
        let line = document.getElementById("element-highlight-line");
        if (!line) {
            line = document.createElement("div");
            line.id = "element-highlight-line";
            overlayContainer.appendChild(line);
        }

        Object.assign(line.style, {
            position: "absolute",
            top: `${top}px`,
            left: "0",
            width: "100%",
            height: "3px",
            backgroundColor: options.lineColor || "rgba(255, 0, 0, 0.4)",
        });

        // Create or update the marker
        const size = options.markerSize || 20;
        const shape = options.markerShape === "square" ? "0" : "50%";
        let marker = document.getElementById("element-highlight-marker");
        if (!marker) {
            marker = document.createElement("div");
            marker.id = "element-highlight-marker";
            overlayContainer.appendChild(marker);
        }

        Object.assign(marker.style, {
            position: "absolute",
            top: `${top - size / 2 + 1.5}px`,
            left: `${left - size / 2}px`,
            width: `${size}px`,
            height: `${size}px`,
            backgroundColor: options.markerColor || "rgba(255, 0, 0, 0.4)",
            borderRadius: shape,
        });

        function isVisible(el) {
            return el.offsetParent !== null || el.getBoundingClientRect().top !== 0;
        }

        target.scrollIntoView({behavior: 'smooth', block: 'center'});
    }
    
    // Console-accessible functions
    window.countAboveFoldElements = countAboveFoldElements;
    window.inspectFoldBreaker = highlightFoldBreakers;
    window.verifyElementMarkerOverlay = highlightElementWithOverlay;
    window.removeFoldOverlay = removeOverlay;

    countAboveFoldElements();

    console.info('▶ To rerun: countAboveFoldElements({numberOfFoldBreaks: N, ignoreTags: false})');
    console.info('▶ Highlight current element marker: verifyElementMarkerOverlay()');
    //console.info('▶ Remove overlay: removeFoldOverlay()');
    //console.info('▶ Highlight fold breakers: inspectFoldBreaker(index=0)');
})();
