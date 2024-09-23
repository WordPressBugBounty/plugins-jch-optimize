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

use _JchOptimizeVendor\Psr\Http\Message\UriInterface;

/**
 * @method Img               alt(string $value)
 * @method Img               crossorigin(?string $value=null)
 * @method Img               decoding(string $value)
 * @method Img               elementtiming(string $value)
 * @method Img               fetchpriority(string $value)
 * @method Img               ismap(string $value)
 * @method Img               loading(string $value)
 * @method Img               referrerpolicy(string $value)
 * @method Img               sizes(string $value)
 * @method Img               src(string|UriInterface $value)
 * @method Img               srcset(string $value)
 * @method Img               width(string $value)
 * @method Img               usemap(string $value)
 * @method Img               height(string $value)
 * @method bool|string       getAlt()
 * @method bool|string       getCrossorigin()
 * @method bool|string       getDecoding()
 * @method bool|string       getElementtiming()
 * @method bool|string       getFetchpriority()
 * @method bool|string       getIsmap()
 * @method bool|string       getLoading()
 * @method bool|string       getReferrerpolicy()
 * @method bool|string       getSizes()
 * @method bool|UriInterface getSrc()
 * @method bool|string       getSrcset()
 * @method bool|string       getWidth()
 * @method bool|string       getUsemap()
 * @method bool|string       getHeight()
 */
final class Img extends \JchOptimize\Core\Html\Elements\BaseElement
{
    protected string $name = 'img';
}
