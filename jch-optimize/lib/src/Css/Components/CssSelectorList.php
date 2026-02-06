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

namespace JchOptimize\Core\Css\Components;

use CodeAlfa\Css2Xpath\Collections\CssSelectorCollection;
use JchOptimize\Core\Css\CssComponents;
use JchOptimize\Core\Css\CssSelectorFactory;

use function implode;

class CssSelectorList extends \CodeAlfa\Css2Xpath\Selector\CssSelectorList implements CssComponents
{
    public static function load(string $css): static
    {
        $selectorFactory = new CssSelectorFactory();

        return parent::create($selectorFactory, $css);
    }

    public function render(?string $axis = null): string
    {
        $selectors = [];

        foreach ($this->selectors as $selector) {
            $selectors[] = $selector->render();
        }

        return implode(',', $selectors);
    }

    public function appendClass(string $class): CssSelectorList
    {
        foreach ($this->selectors as $selector) {
            if ($selector instanceof CssSelector) {
                $selector->appendClass($class);
            }
        }

        return $this;
    }

    public function removePseudoElement(): CssSelectorList
    {
        foreach ($this->selectors as $selector) {
            if ($selector instanceof CssSelector) {
                $selector->removePseudoElement();
            }
        }

        return $this;
    }

    public function __clone()
    {
        $selectors = new CssSelectorCollection();

        foreach ($this->selectors as $selector) {
            $selectors->offsetSet(clone $selector);
        }

        $this->selectors = $selectors;
    }
}
