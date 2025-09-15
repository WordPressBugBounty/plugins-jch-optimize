<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Preloads;

use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use JchOptimize\Core\Html\HtmlElementBuilder;
use JchOptimize\Core\Uri\UriComparator;

class Preload
{
    private string $as;

    private ?string $crossorigin = null;

    private ?string $fetchpriority = null;

    private ?string $imagesizes = null;

    private ?string $imagesrcset = null;

    private ?string $media = null;

    private ?string $type = null;

    private ?string $class = null;

    public function __construct(
        private UriInterface|\Psr\Http\Message\UriInterface $href,
        string $as,
        array $attributes = []
    ) {
        $this->as = $as;

        $this->assignAttributes($attributes);
        $this->setCrossoriginForFonts();
    }

    private function assignAttributes(array $attributes): void
    {
        if (isset($attributes['crossorigin'])) {
            $this->crossorigin = $attributes['crossorigin'];
        }

        if (isset($attributes['fetchpriority']) && $attributes['fetchpriority'] !== 'auto') {
            $this->fetchpriority = $attributes['fetchpriority'];
        }

        if (isset($attributes['imagesizes'])) {
            $this->imagesizes = $attributes['imagesizes'];
        }

        if (isset($attributes['imagesrcset'])) {
            $this->imagesrcset = $attributes['imagesrcset'];
        }

        if (isset($attributes['media'])) {
            $this->media = $attributes['media'];
        }

        if (isset($attributes['type'])) {
            $this->type = $attributes['type'];
        }

        if (isset($attributes['class'])) {
            $this->class = $attributes['class'];
        }
    }

    public function getHref(): UriInterface|\Psr\Http\Message\UriInterface
    {
        return $this->href;
    }

    public function getAs(): ?string
    {
        return $this->as;
    }

    public function getFetchPriority(): ?string
    {
        return $this->fetchpriority;
    }

    public function supportsLinkHeaders(): bool
    {
        return !UriComparator::isCrossOrigin($this->href)
           && $this->imagesizes == null && $this->imagesrcset === null && $this->media == null && $this->class == null;
    }

    private function setCrossoriginForFonts(): void
    {
        if ($this->as == 'font' && $this->crossorigin === null) {
            $this->crossorigin = 'anonymous';
        }
    }

    public function printLinkHeader(): string
    {
        $url = (string) $this->href;
        $type = $this->type !== null ? "; type={$this->type}" : '';
        $fetchpriority = !empty($this->fetchpriority) ? "; fetchpriority={$this->fetchpriority}" : '';
        $crossorigin = $this->crossorigin == 'anonymous' ? '; crossorigin' :
            ($this->crossorigin == 'use-credentials' ? '; crossorigin=use-credentials' : '');

        return "<{$url}>; rel=preload; as={$this->as}{$type}{$fetchpriority}{$crossorigin}";
    }

    public function renderPreloadLink(): string
    {
        $link = HtmlElementBuilder::link()->rel('preload')->attributes([
            'href' => $this->href,
            'as' => $this->as,
        ]);

        if (!empty($this->imagesrcset)) {
            $link->imagesrcset($this->imagesrcset);
        }

        if (!empty($this->imagesizes)) {
            $link->imagesizes($this->imagesizes);
        }

        if (!empty($this->media)) {
            $link->media($this->media);
        }

        if (!empty($this->type)) {
            $link->type($this->type);
        }

        if (!empty($this->fetchpriority)) {
            $link->fetchpriority($this->fetchpriority);
        }

        if (!empty($this->crossorigin)) {
            $link->crossorigin($this->crossorigin);
        }

        if (!empty($this->class)) {
            $link->class($this->class);
        }

        return $link->render();
    }
}
