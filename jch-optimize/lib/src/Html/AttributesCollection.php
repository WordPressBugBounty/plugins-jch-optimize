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
use JchOptimize\Core\Uri\Utils;
use SplObjectStorage;

use function implode;
use function in_array;
use function is_array;
use function is_string;
use function md5;
use function preg_replace;
use function str_contains;

/**
 * @template-extends SplObjectStorage<Attribute, null>
 */
class AttributesCollection extends SplObjectStorage
{
    protected bool $isXhtml;

    protected array $booleanAttributes = [
        'allowfullscreen',
        'async',
        'autofocus',
        'autoplay',
        'checked',
        'controls',
        'default',
        'defer',
        'disabled',
        'formnovalidate',
        'inert',
        'ismap',
        'itemscope',
        'loop',
        'multiple',
        'muted',
        'nomodule',
        'novalidate',
        'open',
        'playsinline',
        'readonly',
        'required',
        'reversed',
        'selected'
    ];

    /**
     * @var string[]
     */
    protected array $enumeratedEmptyStringValue = [
        'crossorigin' => 'anonymous',
    ];

    public function __construct(bool $isXhtml)
    {
        $this->isXhtml = $isXhtml;
    }

    public function setAttribute(string $name, mixed $value, ?string $delimiter = null): void
    {
        $this->rewind();

        while ($this->valid()) {
            $attribute = $this->current();

            if ($attribute->getName() == $name) {
                $attribute->setValue($this->prepareValue($name, $value, $attribute->getValue()));
                $attribute->setDelimiter($delimiter ?? $attribute->getDelimiter());

                return;
            }

            $this->next();
        }

        $value = $this->prepareValue($name, $value);
        $delimiter = $this->prepareDelimiter($delimiter);

        $attribute = new Attribute($name, $value, $delimiter);

        $this->attach($attribute);
    }

    public function getValue(string $name): UriInterface|bool|array|string
    {
        $this->rewind();

        while ($this->valid()) {
            $attribute = $this->current();

            if ($attribute->getName() == $name) {
                $this->rewind();

                return $attribute->getValue();
            }

            $this->next();
        }

        return false;
    }

    /**
     * @param array<string, string|array|bool|UriInterface> $attributes
     * @return void
     */
    public function setAttributes(array $attributes): void
    {
        foreach ($attributes as $name => $value) {
            $this->setAttribute($name, $value);
        }
    }

    public function removeAttribute(string $name): void
    {
        $this->rewind();

        while ($this->valid()) {
            $attribute = $this->current();

            if ($attribute->getName() == $name) {
                $this->detach($attribute);

                return;
            }

            $this->next();
        }
    }

    private function prepareUrlValue(mixed $value): UriInterface
    {
        if (!is_string($value) && !$value instanceof UriInterface) {
            $value = '';
        }

        return Utils::uriFor($value);
    }

    private function prepareClassValue(mixed $value, UriInterface|bool|array|string $prevValue = null): array
    {
        if (!is_string($value) && !is_array($value)) {
            $value = [];
        }

        if (is_string($value)) {
            $value = explode(' ', $value);
        }

        if (is_array($prevValue) && !empty($prevValue)) {
            $value = array_unique(array_filter(array_merge($prevValue, $value)));
        }

        return $value;
    }

    public function render(): string
    {
        $attributeString = '';
        $this->rewind();

        while ($this->valid()) {
            $attribute = $this->current();
            $attributeString .= " {$attribute->getName()}{$this->renderAttributeValue($attribute)}";
            $this->next();
        }

        return $attributeString;
    }

    public function isBoolean(string $name): bool
    {
        return in_array(preg_replace('#^data-#', '', $name), $this->booleanAttributes);
    }

    private function prepareValue(
        string $name,
        mixed $value,
        UriInterface|bool|array|string $prevValue = null
    ): UriInterface|bool|array|string {
        if (array_key_exists($name, $this->enumeratedEmptyStringValue) && empty($value)) {
            $value = $this->enumeratedEmptyStringValue[$name];
        }

        if (preg_match('#^(?:data-)?(?:src|href|poster)$#i', $name)) {
            return $this->prepareUrlValue($value);
        } elseif ($name == 'class') {
            return $this->prepareClassValue($value, $prevValue);
        } elseif ($this->isBoolean($name)) {
            return true;
        } else {
            return (string)$value;
        }
    }

    private function prepareDelimiter(?string $delimiter): string
    {
        return $delimiter ?? '"';
    }

    private function renderAttributeValue(Attribute $attribute): string
    {
        if (!$this->isXhtml) {
            if ($attribute->getValue() === true) {
                return '';
            }

            if (
                isset($this->enumeratedEmptyStringValue[$attribute->getName()])
                && $this->enumeratedEmptyStringValue[$attribute->getName()] == $attribute->getValue()
            ) {
                return '';
            }
        }

        $value = $this->renderValue($attribute);
        $delimiter = $this->renderDelimiter($attribute, $value);

        return "={$delimiter}{$value}{$delimiter}";
    }

    private function renderValue(Attribute $attribute): string
    {
        $value = $attribute->getValue();

        if ($this->isXhtml) {
            if ($value === true) {
                return (string) preg_replace('#^data-#', '', $attribute->getName());
            }
        }

        if (is_array($value)) {
            return implode(' ', $value);
        }

        return (string) $value;
    }

    private function renderDelimiter(Attribute $attribute, string $value): string
    {
        $delimiter = $attribute->getDelimiter();

        if (($this->isXhtml || str_contains($value, ' ')) && $delimiter === '') {
            return '"';
        }

        return $delimiter;
    }

    public function getHash($object): string
    {
        return md5($object->getName());
    }
}
