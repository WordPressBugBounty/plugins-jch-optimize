<?php

namespace JchOptimize\Core\Html\Elements;

use _JchOptimizeVendor\Psr\Http\Message\UriInterface;

/**
 * @method Audio             autoplay(string $value)
 * @method Audio             controls(string $value)
 * @method Audio             crossorigin(?string $value=null)
 * @method Audio             loop(string $value)
 * @method Audio             muted(string $value)
 * @method Audio             preload(string $value)
 * @method Audio             src(string|UriInterface $value)
 * @method bool|string       getAutoplay()
 * @method bool|string       getControls()
 * @method bool|string       getCrossorigin()
 * @method bool|string       getLoop()
 * @method bool|string       getMuted()
 * @method bool|string       getPreload()
 * @method bool|UriInterface getSrc()
 */
final class Audio extends \JchOptimize\Core\Html\Elements\BaseElement
{
    protected string $name = 'audio';
}
