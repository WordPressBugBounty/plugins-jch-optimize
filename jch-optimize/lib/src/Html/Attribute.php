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

class Attribute
{
    private string $name;
    private bool|string|array|UriInterface $value;
    private string $delimiter;

    public function __construct(string $name, string|array|UriInterface|bool $value, string $delimiter)
    {
        $this->name = $name;
        $this->value = $value;
        $this->delimiter = $delimiter;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setValue(string|array|UriInterface|bool $value): void
    {
        $this->value = $value;
    }

    public function getValue(): string|array|UriInterface|bool
    {
        return $this->value;
    }

    public function setDelimiter(string $delimiter): void
    {
        $this->delimiter = $delimiter;
    }

    public function getDelimiter(): string
    {
        return $this->delimiter;
    }
}
