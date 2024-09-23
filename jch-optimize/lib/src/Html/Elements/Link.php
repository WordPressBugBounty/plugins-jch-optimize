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
 * @method Link              as(string $value)
 * @method Link              crossorigin(?string $value=null)
 * @method Link              fetchpriority(string $value)
 * @method Link              href(string|UriInterface $value)
 * @method Link              hreflang(string $value)
 * @method Link              imagesizes(string $value)
 * @method Link              imagesrcset(string $value)
 * @method Link              integrity(string $value)
 * @method Link              media(string $value)
 * @method Link              referrerpolicy(string $value)
 * @method Link              rel(string $value)
 * @method Link              sizes(string $value)
 * @method Link              title(string $value)
 * @method Link              type(string $value)
 * @method bool|string       getAs()
 * @method bool|string       getCrossorigin()
 * @method bool|string       getFetchpriority()
 * @method bool|UriInterface getHref()
 * @method bool|string       getHreflang()
 * @method bool|string       getImagesizes()
 * @method bool|string       getImagesrcset()
 * @method bool|string       getIntegrity()
 * @method bool|string       getMedia()
 * @method bool|string       getReferrerpolicy()
 * @method bool|string       getRel()
 * @method bool|string       getSizes()
 * @method bool|string       getTitle()
 * @method bool|string       getType()
 */
final class Link extends \JchOptimize\Core\Html\Elements\BaseElement
{
    protected string $name = 'link';
}
