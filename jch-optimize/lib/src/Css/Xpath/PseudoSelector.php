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

class PseudoSelector extends \CodeAlfa\Css2Xpath\Selector\PseudoSelector
{
    protected function transformNotSelectorList(string $xpath): string
    {
        return preg_replace("#\[1\]$#", '', parent::transformNotSelectorList($xpath));
    }
}
