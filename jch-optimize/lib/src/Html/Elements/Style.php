<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads.
 *
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Html\Elements;

/**
 * @method Style       media(string $value)
 * @method Style       nonce(string $value)
 * @method Style       title(string $value)
 * @method bool|string getMedia()
 * @method bool|string getNonce()
 * @method bool|string getTitle()
 */
final class Style extends \JchOptimize\Core\Html\Elements\BaseElement
{
    protected string $name = 'style';
}
