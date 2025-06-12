<?php

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

namespace JchOptimize\Core\Css\Xpath;

use CodeAlfa\Css2Xpath\Selector\CssSelector as XpathCssSelector;

class CssSelector extends XpathCssSelector
{
    public function isValid(): bool
    {
        return $this->type
            || $this->id
            || $this->classes->count() > 0
            || $this->attributes->count() > 0
            || $this->pseudoSelectors->count() > 0
            || $this->descendant !== null;
    }

    public function hasPseudoClass(array $pseudoClasses): bool
    {
        /** @var PseudoSelector $pseudoSelector */
        foreach ($this->getPseudoSelectors() as $pseudoSelector) {
            if (in_array($pseudoSelector->getName(), $pseudoClasses)) {
                return true;
            }
        }

        if (($descendant = $this->getDescendant()) instanceof CssSelector) {
            return $descendant->hasPseudoClass($pseudoClasses);
        }

        return false;
    }

    public function render(): string
    {
        return parent::render() . "[1]";
    }
}
