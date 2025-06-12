<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Css\Callbacks;

use JchOptimize\Core\Css\Components\InvalidCssComponent;
use JchOptimize\Core\Css\CssComponents;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

class FormatCss extends AbstractCallback
{
    protected function internalProcessMatches(CssComponents $cssComponent): string
    {
        return $cssComponent->render();
    }

    protected function supportedCssComponents(): array
    {
        return [
            InvalidCssComponent::class
        ];
    }
}
