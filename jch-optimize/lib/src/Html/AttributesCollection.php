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
use JchOptimize\Core\Uri\Utils;

class AttributesCollection
{
    /**
     * @var array<string, Attribute>
     */
    protected array $attributes = [];
    protected bool $isXhtml;
    protected array $booleanAttributes = ['allowfullscreen', 'async', 'autofocus', 'autoplay', 'checked', 'controls', 'default', 'defer', 'disabled', 'formnovalidate', 'inert', 'ismap', 'itemscope', 'loop', 'multiple', 'muted', 'nomodule', 'novalidate', 'open', 'playsinline', 'readonly', 'required', 'reversed', 'selected'];

    /**
     * @var string[]
     */
    protected array $enumeratedEmptyStringValue = ['crossorigin' => 'anonymous', 'preload' => 'auto', 'lazy' => 'eager', 'fetchpriority' => 'auto', 'autocomplete' => 'on', 'hidden' => 'hidden', 'contenteditable' => 'true'];

    public function __construct(bool $isXhtml)
    {
        $this->isXhtml = $isXhtml;
    }

    public function setAttribute(string $name, UriInterface|string|array|bool|null $value, ?string $delimiter = null): void
    {
        if ('src' == $name || 'href' == $name || 'poster' == $name) {
            if (!\is_string($value) && !$value instanceof UriInterface) {
                $value = '';
            }
            $value = $this->prepareUrlValue($value);
        } elseif ('class' == $name) {
            if (!\is_string($value) && !\is_array($value)) {
                $value = [];
            }
            $value = $this->prepareClassValue($value);
        } else {
            if ($value instanceof UriInterface) {
                $value = (string) $value;
            }
            if (null !== $value && \true !== $value && !\is_string($value)) {
                $value = '';
            }
        }
        if ($this->has($name)) {
            $attribute = $this->attributes[$name];
            if (null !== $value) {
                $attribute->setValue($value);
            }
            if (null !== $delimiter) {
                $attribute->setDelimiter($delimiter);
            }
        } else {
            if ($this->isBoolean($name)) {
                $value = \true;
            }
            $value = $value ?? '';
            $delimiter = $delimiter ?? '"';
            $attribute = new \JchOptimize\Core\Html\Attribute($name, $value, $delimiter);
        }
        $this->attributes[$name] = $attribute;
    }

    public function getValue(string $name): UriInterface|bool|array|string
    {
        return $this->attributes[$name]->getValue();
    }

    public function setAttributes(array $attributes): void
    {
        /**
         * @var string                         $name
         * @var array|bool|string|UriInterface $value
         */
        foreach ($attributes as $name => $value) {
            $this->setAttribute($name, $value);
        }
    }

    public function removeAttribute(string $name): void
    {
        if (isset($this->attributes[$name])) {
            unset($this->attributes[$name]);
        }
    }

    public function render(): string
    {
        $attributes = '';
        foreach ($this->attributes as $attribute) {
            $name = $attribute->getName();
            $value = $attribute->getValue();
            $delimiter = $attribute->getDelimiter();
            if ($this->isXhtml) {
                // $value is true for boolean attributes
                if (\true === $value) {
                    $value = \preg_replace('#^data-#', '', $name);
                }
                if (\array_key_exists($name, $this->enumeratedEmptyStringValue) && empty($value)) {
                    $value = $this->enumeratedEmptyStringValue[$name];
                }
                if ('' == $delimiter) {
                    $delimiter = '"';
                }
            }
            if (\is_array($value)) {
                if (\count($value) > 1 && '' == $delimiter) {
                    $delimiter = '"';
                }
                $value = \implode(' ', $value);
            }
            if ($value instanceof UriInterface) {
                $value = (string) $value;
            }
            if ((\true === $attribute->getValue() || \array_key_exists($name, $this->enumeratedEmptyStringValue) && empty($value)) && !$this->isXhtml) {
                $attributes .= " {$name}";
            } else {
                $attributes .= " {$name}={$delimiter}{$value}{$delimiter}";
            }
        }

        return $attributes;
    }

    public function isBoolean(string $name): bool
    {
        return \in_array(\preg_replace('#^data-#', '', $name), $this->booleanAttributes);
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->attributes);
    }

    private function prepareUrlValue(string|UriInterface $value): UriInterface
    {
        return Utils::uriFor($value);
    }

    private function prepareClassValue(array|string $value): array
    {
        if (\is_string($value)) {
            $value = \explode(' ', $value);
        }
        if (isset($this->attributes['class'])) {
            /** @var string[] $classes */
            $classes = $this->attributes['class']->getValue();
            $value = \array_unique(\array_filter(\array_merge($classes, $value)));
        }

        return $value;
    }
}
