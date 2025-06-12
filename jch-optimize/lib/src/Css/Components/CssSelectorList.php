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

namespace JchOptimize\Core\Css\Components;

use JchOptimize\Core\Css\CssComponents;
use JchOptimize\Core\Css\CssSelectorFactory;
use SplObjectStorage;

use function implode;

class CssSelectorList extends \CodeAlfa\Css2Xpath\Selector\CssSelectorList implements CssComponents
{
    public static function load(string $css): static
    {
        $selectorFactory = new CssSelectorFactory();

        return parent::create($selectorFactory, $css);
    }

    public function render(): string
    {
        $selectors = [];

        /** @var CssSelector $selector */
        foreach ($this->selectors as $selector) {
            $selectors[] = $selector->render();
        }

        return implode(',', $selectors);
    }

    public function addClass(string $class): CssSelectorList
    {
        /** @var CssSelector $selector */
        foreach ($this->selectors as $selector) {
            $selector->addClass($class);
        }

        return $this;
    }

    public function removeLastDescendantNonFunctionalPseudoSelectors(): CssSelectorList
    {
        /** @var CssSelector $selector */
        foreach ($this->selectors as $selector) {
            $selector->removeLastDescendantNonFunctionalPseudoSelectors();
        }

        return $this;
    }

    public function __clone()
    {
        /** @var SplObjectStorage<\CodeAlfa\Css2Xpath\Selector\CssSelector, null> $selectors */
        $selectors = new SplObjectStorage();

        foreach ($this->selectors as $selector) {
            $selectors->attach(clone $selector);
        }

        $this->selectors = $selectors;
    }
}
