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
 * @method Script            async()
 * @method Script            crossorigin(?string $value=null)
 * @method Script            defer()
 * @method Script            fetchpriority(string $value)
 * @method Script            integrity(string $value)
 * @method Script            nomodule(string $value)
 * @method Script            nonce(string $value)
 * @method Script            referrerpolicy(string $value)
 * @method Script            src(string|UriInterface $value)
 * @method Script            type(string $value)
 * @method bool              getAsync()
 * @method bool|string       getCrossorigin()
 * @method bool              getDefer()
 * @method bool|string       getFetchpriority()
 * @method bool|string       getIntegrity()
 * @method bool|string       getNomodule()
 * @method bool|string       getNonce()
 * @method bool|string       getReferrerpolicy()
 * @method null|UriInterface getSrc()
 * @method bool|string       getType()
 */
final class Script extends \JchOptimize\Core\Html\Elements\BaseElement
{
    protected string $name = 'script';
}
