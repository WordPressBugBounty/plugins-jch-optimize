<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads.
 *
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Css\Callbacks;

\defined('_JCH_EXEC') or exit('Restricted access');
class FormatCss extends \JchOptimize\Core\Css\Callbacks\AbstractCallback
{
    public string $validCssRules = '';

    public function processMatches(array $matches, string $context): string
    {
        if (isset($matches[7]) && !\preg_match('#'.$this->validCssRules.'#i', $matches[7])) {
            return '';
        }

        return $matches[0];
    }
}
