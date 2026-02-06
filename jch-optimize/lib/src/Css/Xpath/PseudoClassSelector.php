<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/joomla-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Css\Xpath;

use CodeAlfa\Css2Xpath\Selector\PseudoClassSelector as BasePseudoClassSelector;

class PseudoClassSelector extends BasePseudoClassSelector
{
    /**
     * Strict "critical CSS" view of pseudo-classes.
     *
     * - Structural and static-state pseudos delegate to the base implementation.
     * - Pure interaction/dynamic pseudos are treated as never matching.
     * - Any pseudo the base doesn't know (render() === '') is also treated as never matching.
     */
    public function render(): string
    {
        $name = $this->getName();

        // Pseudos that are meaningful for the static DOM / initial render:
        // delegate to the base implementation (general Css2Xpath semantics).
        $delegateToBase = [
            // Control / state
            'enabled',
            'disabled',
            'read-only',
            'read-write',
            'checked',
            'required',

            // Structural
            'root',
            'empty',
            'first-child',
            'last-child',
            'only-child',
            'first-of-type',
            'last-of-type',
            'only-of-type',

            // Nth-*
            'nth-child',
            'nth-last-child',
            'nth-of-type',
            'nth-last-of-type',

            // Functional (selector-list based)
            'not',
            'has',
            'is',
            'where',
        ];

        if (in_array($name, $delegateToBase, true)) {
            return parent::render();
        }

        // Pseudos that are purely interaction / dynamic
        // → never treated as matching in the "above the fold" snapshot.
        $alwaysFalse = [
            'hover',
            'active',
            'focus',
            'focus-within',
            'focus-visible',
            'target',
            'visited',
            'link',
            'any-link',
            'placeholder-shown',
            'autofill',
            'user-invalid',
            'user-valid',
            // add more here as needed
        ];

        if (in_array($name, $alwaysFalse, true)) {
            return "false()";
        }

        // Fallback: ask the base class.
        // If the base returns '' (unknown/unsupported), treat it as non-matching
        // in critical mode instead of "ignore the pseudo".
        $rendered = parent::render();

        return $rendered !== '' ? $rendered : "[false()]";
    }
}
