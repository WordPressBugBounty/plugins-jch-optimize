<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads.
 *
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Html;

use _JchOptimizeVendor\Psr\Http\Message\UriInterface;
use JchOptimize\Core\Html\Elements\BaseElement;

/**
 * @method BaseElement id(string $value)
 * @method BaseElement class(string $value)
 * @method BaseElement hidden(string $value)
 * @method BaseElement style(string $value)
 * @method BaseElement title(string $value)
 * @method bool|string getId()
 * @method array|bool  getClass()
 * @method bool|string getHidden()
 * @method bool|string getStyle()
 * @method bool|string getTitle()
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
}
