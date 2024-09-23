<?php

namespace JchOptimize\Core\Html\Elements;

use _JchOptimizeVendor\Psr\Http\Message\UriInterface;

/**
 * @method Video             autoplay(string $value)
 * @method Video             controls(string $value)
 * @method Video             crossorigin(?string $value=null)
 * @method Video             height(string $value)
 * @method Video             loop(string $value)
 * @method Video             muted(string $value)
 * @method Video             playsinline(string $value)
 * @method Video             poster(string|UriInterface $value)
 * @method Video             preload(string $value)
 * @method Video             src(string $value)
 * @method Video             width(string $value)
 * @method bool|string       getAutoplay()
 * @method bool|string       getControls()
 * @method bool|string       getCrossorigin()
 * @method bool|string       getHeight()
 * @method bool|string       getLoop()
 * @method bool|string       getMuted()
 * @method bool|string       getPlaysinline()
 * @method bool|UriInterface getPoster()
 * @method bool|string       getPreload()
 * @method bool|string       getSrc()
 * @method bool|string       getWidth()
 */
final class Video extends \JchOptimize\Core\Html\Elements\BaseElement
{
    protected string $name = 'video';
}
