<?php

namespace JchOptimize\Core\Html\Elements;

use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;

/**
 * @method Input alt(string $value)
 * @method Input autocomplete(string $value)
 * @method Input disabled(string $value)
 * @method Input form(string $value)
 * @method Input name(string $value)
 * @method Input readonly(string $value)
 * @method Input required(string $value)
 * @method Input height(string $value)
 * @method Input src(string|UriInterface $value)
 * @method Input type(string $value)
 * @method Input width(string $value)
 * @method string|false getAlt()
 * @method string|false getAutocomplete()
 * @method string|false getDisabled()
 * @method string|false getForm()
 * @method string|false getName()
 * @method string|false getReadonly()
 * @method string|false getRequired()
 * @method string|false getHeight()
 * @method UriInterface|false getSrc()
 * @method string|false getType()
 * @method string|false getWidth()
 */
final class Input extends BaseElement
{
    protected string $name = 'input';
}
