<?php

namespace JchOptimize\Core\Html\Elements;

use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;

/**
 * @method Audio autoplay(string $value)
 * @method Audio controls(string $value)
 * @method Audio crossorigin(?string $value=null)
 * @method Audio loop(string $value)
 * @method Audio muted(string $value)
 * @method Audio preload(string $value)
 * @method Audio src(string|UriInterface $value)
 * @method string|false getAutoplay()
 * @method string|false getControls()
 * @method string|false getCrossorigin()
 * @method string|false getLoop()
 * @method string|false getMuted()
 * @method string|false getPreload()
 * @method UriInterface|false getSrc()
 */
final class Audio extends BaseElement
{
    protected string $name = 'audio';
}
