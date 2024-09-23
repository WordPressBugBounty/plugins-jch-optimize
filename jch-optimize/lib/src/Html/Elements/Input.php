<?php

namespace JchOptimize\Core\Html\Elements;

use _JchOptimizeVendor\Psr\Http\Message\UriInterface;

/**
 * @method Input             alt(string $value)
 * @method Input             autocomplete(string $value)
 * @method Input             disabled(string $value)
 * @method Input             form(string $value)
 * @method Input             name(string $value)
 * @method Input             readonly(string $value)
 * @method Input             required(string $value)
 * @method Input             height(string $value)
 * @method Input             src(string|UriInterface $value)
 * @method Input             type(string $value)
 * @method Input             width(string $value)
 * @method bool|string       getAlt()
 * @method bool|string       getAutocomplete()
 * @method bool|string       getDisabled()
 * @method bool|string       getForm()
 * @method bool|string       getName()
 * @method bool|string       getReadonly()
 * @method bool|string       getRequired()
 * @method bool|string       getHeight()
 * @method bool|UriInterface getSrc()
 * @method bool|string       getType()
 * @method bool|string       getWidth()
 */
final class Input extends \JchOptimize\Core\Html\Elements\BaseElement
{
    protected string $name = 'input';
}
