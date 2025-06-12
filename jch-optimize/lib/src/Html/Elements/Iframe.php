<?php

namespace JchOptimize\Core\Html\Elements;

use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;

/**
 * @method Iframe allow(string $value)
 * @method Iframe allowfullscreen(string $value)
 * @method Iframe height(string $value)
 * @method Iframe loading(string $value)
 * @method Iframe name(string $value)
 * @method Iframe referrerpolicy(string $value)
 * @method Iframe sandbox(string $value)
 * @method Iframe src(string|UriInterface $value)
 * @method Iframe srcdoc(string $value)
 * @method Iframe width(string $value)
 * @method string|false getAllow()
 * @method string|false getAllowfullscreen()
 * @method string|false getHeight()
 * @method string|false getLoading()
 * @method string|false getName()
 * @method string|false getReferrerpolicy()
 * @method string|false getSandbox()
 * @method UriInterface|false getSrc()
 * @method string|false getSrcdoc()
 * @method string|false getWidth()
 */
final class Iframe extends BaseElement
{
    protected string $name = 'iframe';
}
