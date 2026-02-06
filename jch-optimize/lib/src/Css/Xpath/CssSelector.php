<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Css\Xpath;

use CodeAlfa\Css2Xpath\Selector\CssSelector as XpathCssSelector;

use function in_array;
use function preg_match;

class CssSelector extends XpathCssSelector
{
    public function isValid(): bool
    {
        return $this->type
            || $this->id
            || (int)$this->classes?->count() > 0
            || (int)$this->attributes?->count() > 0
            || (int)$this->pseudoClasses?->count() > 0
            || $this->pseudoElement
            || $this->descendant !== null;
    }

    public function hasPseudoClass(array $pseudoClasses): bool
    {
        foreach ($this->getPseudoClasses() as $pseudoSelector) {
            if (in_array($pseudoSelector->getName(), $pseudoClasses)) {
                return true;
            }
        }

        if (($descendant = $this->getDescendant()) instanceof CssSelector) {
            return $descendant->hasPseudoClass($pseudoClasses);
        }

        return false;
    }

    public function hasPseudoElement(): bool
    {
        if ($this->getPseudoElement()) {
            return true;
        }

        if (($descendant = $this->getDescendant()) instanceof CssSelector) {
            return $descendant->hasPseudoElement();
        }

        return false;
    }

    /**
     * Render each top-level CSS selector as XPath, optionally restricted to
     * the first match of each branch by appending [1].
     *
     * Pseudo-selectors inside the branch stay unchanged because we only
     * post-process the top-level branch string returned by CssSelector::render().
     */
    public function renderFirstPerBranch(?string $axis = null): string
    {
        // This will always be your CssSelector subclass, but keep it generic.
        $xpath = $this->render($axis);

        return $this->appendFirstPredicate($xpath);
    }

    /**
     * Append [1] to the end of the branch if it’s not already there.
     *
     * Because CssSelector::render() always returns something of the form:
     *   <axis>::<node>[pred][pred]...
     * this "[1]" applies to the whole node-set for that branch.
     */
    private function appendFirstPredicate(string $xpath): string
    {
        // Be defensive: don’t double-up if some other caller already did this.
        if ($xpath === '' || preg_match('/\[\s*1\s*]$/', $xpath)) {
            return $xpath;
        }

        return $xpath . '[1]';
    }
}
