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

class Preconnect
{
    private UriInterface $href;

    private ?string $crossorigin;

    public function __construct(UriInterface $href, ?string $crossorigin = null)
    {
        $this->href = $href->withPath('')->withQuery('')->withFragment('');
        $this->crossorigin = $crossorigin;
    }

    public function getHref(): UriInterface
    {
        return $this->href;
    }

    public function getCrossorigin(): ?string
    {
        return $this->crossorigin;
    }
    public function render(): string
    {
        $link = HtmlElementBuilder::link()->rel('preconnect')->href($this->getHref());

        if (!empty($this->getCrossorigin())) {
            $link->crossorigin($this->getCrossorigin());
        }

        return $link->render();
    }
}
