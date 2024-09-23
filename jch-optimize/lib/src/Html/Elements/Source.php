<?php

namespace JchOptimize\Core\Html\Elements;

use _JchOptimizeVendor\Psr\Http\Message\UriInterface;

/**
 * @method Source            type(string $value)
 * @method Source            src(string|UriInterface $value)
 * @method Source            srcset(string $value)
 * @method Source            sizes(string $value)
 * @method Source            media(string $value)
 * @method Source            height(string $value)
 * @method Source            width(string $value)
 * @method bool|string       getType()
 * @method bool|UriInterface getSrc()
 * @method bool|string       getSrcset()
 * @method bool|string       getSizes()
 * @method bool|string       getMedia()
 * @method bool|string       getHeight()
 * @method bool|string       getWidth()
 */
final class Source extends \JchOptimize\Core\Html\Elements\BaseElement
{
    protected string $name = 'source';
}
