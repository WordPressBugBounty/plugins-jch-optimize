<?php

namespace JchOptimize\Core\Html\Elements;

use _JchOptimizeVendor\Psr\Http\Message\UriInterface;

/**
 * @method Iframe            allow(string $value)
 * @method Iframe            allowfullscreen(string $value)
 * @method Iframe            height(string $value)
 * @method Iframe            loading(string $value)
 * @method Iframe            name(string $value)
 * @method Iframe            referrerpolicy(string $value)
 * @method Iframe            sandbox(string $value)
 * @method Iframe            src(string|UriInterface $value)
 * @method Iframe            srcdoc(string $value)
 * @method Iframe            width(string $value)
 * @method bool|string       getAllow()
 * @method bool|string       getAllowfullscreen()
 * @method bool|string       getHeight()
 * @method bool|string       getLoading()
 * @method bool|string       getName()
 * @method bool|string       getReferrerpolicy()
 * @method bool|string       getSandbox()
 * @method bool|UriInterface getSrc()
 * @method bool|string       getSrcdoc()
 * @method bool|string       getWidth()
 */
final class Iframe extends \JchOptimize\Core\Html\Elements\BaseElement
{
    protected string $name = 'iframe';
}
