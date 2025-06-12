<?php

namespace JchOptimize\Core\Html\Elements;

use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;

/**
 * @method Source type(string $value)
 * @method Source src(string|UriInterface $value)
 * @method Source srcset(string $value)
 * @method Source sizes(string $value)
 * @method Source media(string $value)
 * @method Source height(string $value)
 * @method Source width(string $value)
 * @method string|false getType()
 * @method UriInterface|false getSrc()
 * @method string|false getSrcset()
 * @method string|false getSizes()
 * @method string|false getMedia()
 * @method string|false getHeight()
 * @method string|false getWidth()
 */
final class Source extends BaseElement
{
    protected string $name = 'source';
}
