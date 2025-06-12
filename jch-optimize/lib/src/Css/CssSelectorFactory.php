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

namespace JchOptimize\Core\Css;

use JchOptimize\Core\Css\Components\CssSelector;
use JchOptimize\Core\Css\Components\CssSelectorList;
use CodeAlfa\Css2Xpath\SelectorFactory;
use CodeAlfa\Css2Xpath\SelectorFactoryInterface;

class CssSelectorFactory extends SelectorFactory
{
    public function createCssSelectorList(
        SelectorFactoryInterface $selectorFactory,
        string $cssSelectorList
    ): CssSelectorList {
        return CssSelectorList::create($selectorFactory, $cssSelectorList);
    }

    public function createCssSelector(SelectorFactoryInterface $selectorFactory, string $cssSelector): CssSelector
    {
        return CssSelector::create($selectorFactory, $cssSelector);
    }
}
