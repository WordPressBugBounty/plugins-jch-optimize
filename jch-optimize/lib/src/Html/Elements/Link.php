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
 * @method Link as(string $value)
 * @method Link crossorigin(?string $value=null)
 * @method Link fetchpriority(string $value)
 * @method Link href(string|UriInterface $value)
 * @method Link hreflang(string $value)
 * @method Link imagesizes(string $value)
 * @method Link imagesrcset(string $value)
 * @method Link integrity(string $value)
 * @method Link media(string $value)
 * @method Link referrerpolicy(string $value)
 * @method Link rel(string $value)
 * @method Link sizes(string $value)
 * @method Link title(string $value)
 * @method Link type(string $value)
 * @method string|false getAs()
 * @method string|false getCrossorigin()
 * @method string|false getFetchpriority()
 * @method UriInterface|false getHref()
 * @method string|false getHreflang()
 * @method string|false getImagesizes()
 * @method string|false getImagesrcset()
 * @method string|false getIntegrity()
 * @method string|false getMedia()
 * @method string|false getReferrerpolicy()
 * @method string|false getRel()
 * @method string|false getSizes()
 * @method string|false getTitle()
 * @method string|false getType()
 */
final class Link extends BaseElement
{
    protected string $name = 'link';

    protected bool $omitClosingTag = true;
}
