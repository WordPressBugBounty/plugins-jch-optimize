<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Html\Elements;

use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;

/**
 * @method Img alt(string $value)
 * @method Img crossorigin(?string $value=null)
 * @method Img decoding(string $value)
 * @method Img elementtiming(string $value)
 * @method Img fetchpriority(string $value)
 * @method Img ismap(string $value)
 * @method Img loading(string $value)
 * @method Img referrerpolicy(string $value)
 * @method Img sizes(string $value)
 * @method Img src(string|UriInterface $value)
 * @method Img srcset(string $value)
 * @method Img width(string $value)
 * @method Img usemap(string $value)
 * @method Img height(string $value)
 * @method string|false getAlt()
 * @method string|false getCrossorigin()
 * @method string|false getDecoding()
 * @method string|false getElementtiming()
 * @method string|false getFetchpriority()
 * @method string|false getIsmap()
 * @method string|false getLoading()
 * @method string|false getReferrerpolicy()
 * @method string|false getSizes()
 * @method UriInterface|false getSrc()
 * @method string|false getSrcset()
 * @method string|false getWidth()
 * @method string|false getUsemap()
 * @method string|false getHeight()
 */
final class Img extends BaseElement
{
    protected string $name = 'img';
}
