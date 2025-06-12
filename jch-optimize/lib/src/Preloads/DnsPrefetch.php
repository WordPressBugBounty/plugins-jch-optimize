<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Preloads;

use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use JchOptimize\Core\Html\HtmlElementBuilder;

class DnsPrefetch
{
    private UriInterface $href;

    public function __construct(UriInterface $href)
    {
        $this->href = $href->withPath('')->withQuery('')->withFragment('');
    }

    public function getHref(): UriInterface
    {
        return $this->href;
    }

    public function render(): string
    {
        $link = HtmlElementBuilder::link()->rel('dns-prefetch')->href($this->getHref());

        return $link->render();
    }
}
