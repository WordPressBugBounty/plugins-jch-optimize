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

use CodeAlfa\Css2Xpath\SelectorFactory as XpathSelectorFactory;
use CodeAlfa\Css2Xpath\SelectorFactoryInterface;

class SelectorFactory extends XpathSelectorFactory
{
    public function createCssSelector(SelectorFactoryInterface $selectorFactory, string $cssSelector): CssSelector
    {
        return CssSelector::create($selectorFactory, $cssSelector);
    }

    public function createPseudoClassSelector(
        SelectorFactoryInterface $selectorFactory,
        string $name,
        ?string $selectorList = null,
        string $modifier = '',
        ?string $elementName = null
    ): PseudoClassSelector {
        return new PseudoClassSelector($selectorFactory, $name, $selectorList, $modifier, $elementName);
    }
}
