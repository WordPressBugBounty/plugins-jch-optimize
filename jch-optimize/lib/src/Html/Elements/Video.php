<?php

namespace JchOptimize\Core\Html\Elements;

use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;

/**
 * @method Video autoplay(string $value)
 * @method Video controls(string $value)
 * @method Video crossorigin(?string $value=null)
 * @method Video height(string $value)
 * @method Video loop(string $value)
 * @method Video muted(string $value)
 * @method Video playsinline(string $value)
 * @method Video poster(string|UriInterface $value)
 * @method Video preload(string $value)
 * @method Video src(string $value)
 * @method Video width(string $value)
 * @method string|false getAutoplay()
 * @method string|false getControls()
 * @method string|false getCrossorigin()
 * @method string|false getHeight()
 * @method string|false getLoop()
 * @method string|false getMuted()
 * @method string|false getPlaysinline()
 * @method UriInterface|false getPoster()
 * @method string|false getPreload()
 * @method UriInterface|false getSrc()
 * @method string|false getWidth()
 */
final class Video extends BaseElement
{
    protected string $name = 'video';
}
