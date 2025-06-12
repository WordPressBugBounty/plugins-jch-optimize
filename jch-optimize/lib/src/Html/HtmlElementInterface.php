<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Html;

use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;

/**
 * @method static id(string $value)
 * @method static class(string $value)
 * @method static hidden(string $value)
 * @method static style(string $value)
 * @method static title(string $value)
 * @method string|false getId()
 * @method array|false getClass()
 * @method string|false getHidden()
 * @method string|false getStyle()
 * @method string|false getTitle()
 */
interface HtmlElementInterface
{
    public function attribute(string $name, string $value = '', string $delimiter = '"'): static;

    public function hasAttribute(string $name): bool;

    public function attributeValue(string $name): UriInterface|array|string|bool;

    public function remove(string $name): static;

    public function addChild(HtmlElementInterface|string $child): static;

    public function addChildren(array $children): static;

    public function hasChildren(): bool;

    public function replaceChild(int $index, $child): static;

    public function render(): string;

    public function firstOfAttributes(array $attributes): UriInterface|array|string|bool;

    public function data(string $name, UriInterface|array|string $value = ''): static;

    public function getChildren(): array;

    public function setParent(string $name): static;

    public function getParent(): string;

    public function __toString(): string;
}
