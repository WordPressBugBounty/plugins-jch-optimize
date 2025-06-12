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

namespace JchOptimize\Core\Html\Elements;

use _JchOptimizeVendor\V91\Psr\Container\ContainerExceptionInterface;
use _JchOptimizeVendor\V91\Psr\Container\ContainerInterface;
use _JchOptimizeVendor\V91\Psr\Container\NotFoundExceptionInterface;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Html\Attribute;
use JchOptimize\Core\Html\AttributesCollection;
use JchOptimize\Core\Html\HtmlElementBuilder;
use JchOptimize\Core\Html\HtmlElementInterface;
use JchOptimize\Core\Html\HtmlProcessor;

use function array_merge;
use function strtolower;

/**
 * @method BaseElement id(string $value)
 * @method BaseElement class(string $value)
 * @method BaseElement hidden(string $value)
 * @method BaseElement style(string $value)
 * @method BaseElement title(string $value)
 * @method string|false getId()
 * @method array|false getClass()
 * @method string|false getHidden()
 * @method string|false getStyle()
 * @method string|false getTitle()
 */
class BaseElement implements HtmlElementInterface
{
    protected AttributesCollection $attributes;

    protected string $name = '';

    protected bool $isXhtml = false;

    protected string $parent = '';

    /**
     * @var (HtmlElementInterface|string)[]
     */
    protected array $children = [];

    protected bool $omitClosingTag = false;

    public function __construct(ContainerInterface $container)
    {
        try {
            $htmlProcessor = $container->get(HtmlProcessor::class);
            $this->isXhtml = Helper::isXhtml($htmlProcessor->getHtml());
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
        }

        $this->attributes = new AttributesCollection($this->isXhtml);
    }

    public function render(): string
    {
        $html = "<{$this->name}";
        $html .= $this->attributes->render();
        $html .= ($this->isVoidElement($this->name) && $this->isXhtml) ? ' />' : '>';

        if (
            $this->hasChildren() ||
            (!$this->isVoidElement($this->name) && !$this->omitClosingTag)
        ) {
            $html .= "{$this->renderChildren()}</{$this->name}>";
        }

        return $html;
    }

    public function attribute(
        string $name,
        mixed $value = '',
        ?string $delimiter = null
    ): static {
        $this->attributes->setAttribute($name, $value, $delimiter);

        return $this;
    }

    public function remove(string $name): static
    {
        $this->attributes->removeAttribute($name);

        return $this;
    }

    public function attributes(array $attributes): static
    {
        $this->attributes->setAttributes($attributes);

        return $this;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return static|array|bool|string|UriInterface
     */
    public function __call(string $name, array $arguments)
    {
        if (str_starts_with($name, 'get')) {
            $name = strtolower(substr($name, 3));

            return $this->attributeValue($name);
        }

        $value = $arguments[0] ?? '';
        $delimiter = $arguments[1] ?? null;

        return $this->attribute($name, $value, $delimiter);
    }

    public function data(string $name, UriInterface|array|string $value = ''): static
    {
        $this->attribute('data-' . $name, $value);

        return $this;
    }

    public function addChild(HtmlElementInterface|string $child): static
    {
        $this->children[] = $child;

        return $this;
    }

    public function addChildren(array $children): static
    {
        $this->children = array_merge($this->children, $children);

        return $this;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getElementName(): string
    {
        return $this->name;
    }

    public function attributeValue(string $name): UriInterface|bool|array|string
    {
        return $this->attributes->getValue($name);
    }

    public function hasAttribute(string $name): bool
    {
        return ($this->attributes->getValue($name) !== false);
    }

    public function firstOfAttributes(array $attributes): UriInterface|bool|array|string
    {
        foreach ($attributes as $name => $value) {
            if (($retrievedValue = $this->attributes->getValue($name)) !== false) {
                if ($this->attributes->isBoolean($name)) {
                    return $name;
                } elseif ($retrievedValue === $value) {
                    return $value;
                }
            }
        }

        return false;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @param int $index
     * @param HtmlElementInterface|string $child
     * @return static
     */
    public function replaceChild(int $index, $child): static
    {
        $this->children[$index] = $child;

        return $this;
    }

    public function hasChildren(): bool
    {
        return !empty($this->children);
    }

    public function __toString(): string
    {
        return $this->render();
    }

    public function isVoidElement(string $name): bool
    {
        return in_array($name, HtmlElementBuilder::$voidElements);
    }

    private function renderChildren(): string
    {
        $contents = '';

        foreach ($this->children as $child) {
            if ($child instanceof HtmlElementInterface) {
                $contents .= $child->render();
            } else {
                $contents .= $child;
            }
        }

        return $contents;
    }

    public function setOmitClosingTag(bool $flag): static
    {
        $this->omitClosingTag = $flag;

        return $this;
    }

    public function setParent(string $name): static
    {
        $this->parent = $name;

        return $this;
    }

    public function getParent(): string
    {
        return $this->parent;
    }

    public function __clone()
    {
        $attributes = new AttributesCollection($this->isXhtml);

        foreach ($this->attributes as $attribute) {
            $name = $attribute->getName();
            $value = $attribute->getValue();
            $delimiter = $attribute->getDelimiter();

            if ($value instanceof UriInterface) {
                $value = clone $value;
            }

            $newAttribute = new Attribute($name, $value, $delimiter);
            $attributes->attach($newAttribute);
        }

        $this->attributes = $attributes;
    }
}
