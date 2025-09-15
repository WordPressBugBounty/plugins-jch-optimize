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

document.addEventListener('DOMContentLoaded', () => {
    console.info('Checking for Dynamic CSS Selectors. Please wait...');
    const observedSelectors = new Set();
    let finalSelectors = [];

    const simplify = (items) => {
        const raw = [...items];

        // ---------- PASS 1: Longest-prefix reduction (threshold > 3) ----------
        // Build prefix -> Set(selectors that start with it), for prefixes length >= 4
        const prefixMap = new Map();
        for (const s of raw) {
            for (let i = 4; i <= s.length; i++) {
                const p = s.slice(0, i);
                let set = prefixMap.get(p);
                if (!set) prefixMap.set(p, (set = new Set()));
                set.add(s);
            }
        }

        // Candidates that cover > 3 selectors, pick LONGEST first
        const candidates = [...prefixMap.entries()]
            .filter(([, set]) => set.size > 3)
            .sort((a, b) => b[0].length - a[0].length);

        const chosenPrefixes = [];
        const covered = new Set();

        for (const [p, set] of candidates) {
            // If an already-chosen LONGER prefix starts with this p, skip p (redundant broader)
            const shadowedByLonger = chosenPrefixes.some(lp => lp.startsWith(p));
            if (shadowedByLonger) continue;

            chosenPrefixes.push(p);
            for (const s of set) covered.add(s);
        }

        // After pass 1: keep chosen longest prefixes + any selectors not covered by them
        const pass1Set = new Set([...chosenPrefixes, ...raw.filter(s => !covered.has(s))]);

        // ---------- PASS 2: Exact-first collapse (shorter/broader wins) ----------
        // Sort by length ASC so exact/shorter prefixes are seen first
        const pass1Arr = [...pass1Set].sort((a, b) => (a.length - b.length) || (a < b ? -1 : 1));
        const out = [];

        for (const current of pass1Arr) {
            // If 'current' starts with any already kept prefix of length >= 4, drop it
            const hasAncestor = out.some(existing => existing.length >= 4 && current.startsWith(existing));
            if (!hasAncestor) out.push(current);
        }

        // ---------- Final formatting (preserve your ZWSP behavior) ----------
        const fmt = (s) => {
            if (!s.startsWith('.') && !s.startsWith('#') && !s.startsWith('[')) {
                // Tag names -> wrap both sides with ZWSP so they don't get glued to neighbors
                return '\u200B' + s + '\u200B';
            }
            // Very short fragments get a trailing ZWSP (matches your original)
            return s.length < 4 ? s + '\u200B' : s;
        };

        return [...new Set(out.map(fmt))].sort();
    };

    const isInTargetArea = (el) => {
        const rect = el.getBoundingClientRect();
        return rect.top >= 0 && rect.top <= (window.innerHeight + 100);
    };

    const extractSelectors = (node) => {
        const results = new Set();

        if (node.nodeType !== Node.ELEMENT_NODE) return results;
        if (!isInTargetArea(node)) return results;

        const popularTags = ['a', 'div', 'span', 'img', 'p', 'h1', 'h2', 'ul', 'ol', 'li'];
        const tagName = node.tagName.toLowerCase();

        if (!popularTags.includes(tagName)) {
            results.add(node.tagName.toLowerCase());
        }

        if (node.id) results.add(`#${node.id}`);
        if (node.classList && node.classList.length > 0) {
            node.classList.forEach(cls => results.add(`.${cls}`));
        }

        for (const attr of node.attributes) {
            if (attr.name !== 'id' && attr.name !== 'class') {
                results.add(`[${attr.name}`);
            }
        }

        return results;
    };

    const extractAttributeChanges = (mutation) => {
        const results = new Set();
        const el = mutation.target;
        if (!isInTargetArea(el)) return results;

        const attrName = mutation.attributeName;
        const oldValue = mutation.oldValue || '';
        const newValue = el.getAttribute(attrName) || '';

        if (attrName === 'class') {
            const oldClasses = new Set(oldValue.split(/\s+/));
            const newClasses = new Set(newValue.split(/\s+/));
            for (const cls of newClasses) {
                if (cls && !oldClasses.has(cls)) {
                    results.add(`.${cls}`);
                }
            }
        } else if (attrName === 'id') {
            if (newValue && newValue !== oldValue) {
                results.add(`#${newValue}`);
            }
        } else {
            if (newValue && newValue !== oldValue) {
                results.add(`[${attrName}`);
            }
        }

        return results;
    };

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (mutation.type === 'childList') {
                for (const node of mutation.addedNodes) {
                    const stack = [node];
                    while (stack.length > 0) {
                        const current = stack.pop();
                        extractSelectors(current).forEach(sel => observedSelectors.add(sel));
                        if (current.children) {
                            stack.push(...current.children);
                        }
                    }
                }
            } else if (mutation.type === 'attributes') {
                extractAttributeChanges(mutation).forEach(sel => observedSelectors.add(sel));
            }
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeOldValue: true,
    });

    window.downloadDynamicSelectors = () => {
        const json = JSON.stringify({merge: true, pro_dynamic_selectors: finalSelectors}, null, 2);
        const blob = new Blob([json], {type: 'application/json'});
        const url = URL.createObjectURL(blob);

        const a = document.createElement('a');
        a.href = url;
        a.download = 'dynamic_selectors.json';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    // Wait until full load before printing/logging to avoid interfering with rendering
    window.addEventListener('load', () => {
        setTimeout(() => {
            observer.disconnect();
            finalSelectors = simplify(observedSelectors);
            finalSelectors.sort();

            if (finalSelectors.length > 0) {
                console.table(finalSelectors.map(selector => ({'CSS Dynamic Selectors': selector})));
                console.info('✅ Selector tracking complete.');
                console.info('▶ Run to download: downloadDynamicSelectors()');
            } else {
                console.info('ℹ️ No Dynamic CSS Selectors detected.');
            }
        }, 3000);
    });
});
