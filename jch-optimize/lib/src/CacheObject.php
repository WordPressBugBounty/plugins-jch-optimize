<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/joomla-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core;

use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use JchOptimize\Core\Css\Components\CssSelectorList;

use function array_merge;
use function md5;
use function time;

class CacheObject
{
    private ?CacheObject $importedContents = null;

    private int $filemtime;

    private string $etag;

    private string $contents = '';

    private string $imports = '';

    private string $criticalCss = '';

    private string $potentialCriticalCssAtRules = '';

    private string $belowFoldFontsKeyFrame = '';

    private array $images = [];

    private array $fontFace = [];

    private array $gFonts = [];

    private array $prefetches = [];

    private array $bgSelectors = [];

    private array $lcpImages = [];

    private string $criticalCssId = '';

    private array $http2Preloads = [];

    public function getEtag(): string
    {
        return $this->etag;
    }

    public function setFilemtime(?int $filemtime = null): CacheObject
    {
        $this->filemtime = $filemtime ?? time();

        return $this;
    }

    public function getFilemtime(): int
    {
        return $this->filemtime;
    }

    public function setImports(string $imports): static
    {
        $this->imports = $imports;

        return $this;
    }

    public function addImports(string $imports): static
    {
        $this->imports .= $imports;

        return $this;
    }

    public function setImages(array $images): static
    {
        $this->images = $images;

        return $this;
    }

    public function addImages(string|UriInterface $images): static
    {
        $this->images[] = $images;

        return $this;
    }

    public function setFontFace(array $fontFace): static
    {
        $this->fontFace = $fontFace;

        return $this;
    }

    public function addFontFace(array $fontFace): static
    {
        $this->fontFace[] = $fontFace;

        return $this;
    }

    public function setGFonts(array $gFonts): static
    {
        $this->gFonts = $gFonts;

        return $this;
    }

    public function addGFonts(array $gFont): static
    {
        $this->gFonts[] = $gFont;

        return $this;
    }

    public function setPrefetches(array $prefetches): static
    {
        $this->prefetches = $prefetches;

        return $this;
    }

    public function addPrefetches(UriInterface $prefetch): static
    {
        $this->prefetches[] = $prefetch;

        return $this;
    }

    public function setBgSelectors(array $bgSelectors): static
    {
        $this->bgSelectors = $bgSelectors;

        return $this;
    }

    public function addBgSelectors(CssSelectorList $bgSelectors): static
    {
        $this->bgSelectors[] = $bgSelectors;

        return $this;
    }

    public function setLcpImages(array $lcpImages): static
    {
        $this->lcpImages = $lcpImages;

        return $this;
    }

    public function addLcpImages(array $lcpImage): static
    {
        $this->lcpImages[] = $lcpImage;

        return $this;
    }

    public function getGFonts(): array
    {
        return $this->gFonts;
    }

    public function getImages(): array
    {
        return $this->images;
    }

    public function getFontFace(): array
    {
        return $this->fontFace;
    }

    public function getPrefetches(): array
    {
        return $this->prefetches;
    }

    public function getBgSelectors(): array
    {
        return $this->bgSelectors;
    }

    public function getLcpImages(): array
    {
        return $this->lcpImages;
    }

    public function merge(CacheObject $object): static
    {
        $this->internalMergeImportedContents($object);
        $this->contents .= $object->getContents();
        $this->imports .= $object->getImports();
        $this->criticalCss .= $object->getCriticalCss();
        $this->potentialCriticalCssAtRules .= $object->getPotentialCriticalCssAtRules();
        $this->belowFoldFontsKeyFrame .= $object->getBelowFoldFontsKeyFrame();
        $this->images = array_merge($this->images, $object->getImages());
        $this->fontFace = array_merge($this->fontFace, $object->getFontFace());
        $this->gFonts = array_merge($this->gFonts, $object->getGFonts());
        $this->prefetches = array_merge($this->prefetches, $object->getPrefetches());
        $this->bgSelectors = array_merge($this->bgSelectors, $object->getBgSelectors());
        $this->lcpImages = array_merge($this->lcpImages, $object->getLcpImages());
        $this->http2Preloads = array_merge($this->http2Preloads, $object->getHttp2Preloads());

        return $this;
    }

    public function getContents(): string
    {
        return $this->contents;
    }

    public function setContents(string $contents): static
    {
        $this->contents = $contents;

        return $this;
    }

    public function appendContents(string $contents): static
    {
        $this->contents .= $contents;

        return $this;
    }

    public function prependContents(string $contents): static
    {
        $this->contents = $contents . $this->contents;

        return $this;
    }

    public function getImports(): string
    {
        return $this->imports;
    }

    public function calculateEtag(): CacheObject
    {
        $this->etag = md5($this->contents);

        return $this;
    }

    public function setCriticalCss(string $criticalCss): CacheObject
    {
        $this->criticalCss = $criticalCss;

        return $this;
    }

    public function appendCriticalCss(string $criticalCss): CacheObject
    {
        $this->criticalCss .= $criticalCss;

        return $this;
    }

    public function setPotentialCriticalCssAtRules(string $potentialCriticalCssAtRules): CacheObject
    {
        $this->potentialCriticalCssAtRules = $potentialCriticalCssAtRules;

        return $this;
    }

    public function getPotentialCriticalCssAtRules(): string
    {
        return $this->potentialCriticalCssAtRules;
    }

    public function getCriticalCss(): string
    {
        return $this->criticalCss;
    }

    public function getBelowFoldFontsKeyFrame(): string
    {
        return $this->belowFoldFontsKeyFrame;
    }

    public function setBelowFoldFontsKeyFrame(string $belowFoldFontsKeyFrame): static
    {
        $this->belowFoldFontsKeyFrame = $belowFoldFontsKeyFrame;

        return $this;
    }

    public function prepareForCaching(): void
    {
        $this->calculateEtag();
        $this->setFilemtime();
    }

    public function setCriticalCssId(string $id): static
    {
        $this->criticalCssId = $id;

        return $this;
    }

    public function getCriticalCssId(): string
    {
        return $this->criticalCssId;
    }

    public function getHttp2Preloads(): array
    {
        return $this->http2Preloads;
    }

    public function setHttp2Preloads(array $http2Preloads): static
    {
        $this->http2Preloads = $http2Preloads;

        return $this;
    }

    public function addHttp2Preloads(array $http2Preload): static
    {
        $this->http2Preloads[] = $http2Preload;

        return $this;
    }

    public function setImportedContents(CacheObject $importedContents): CacheObject
    {
        $this->importedContents = $importedContents;

        return $this;
    }

    public function getMergedImportedContents(): CacheObject
    {
        if ($this->importedContents instanceof CacheObject) {
            $mergedImportObj = $this->importedContents->getMergedImportedContents();
            $mergedImportObj->merge($this);
            $mergedImportObj->importedContents = null;

            return $mergedImportObj;
        }

        return $this;
    }

    private function internalMergeImportedContents(CacheObject $object): void
    {
        if ($object->importedContents instanceof self) {
            if ($this->importedContents instanceof self) {
                $this->importedContents->merge($object->importedContents);
            } elseif ($this->importedContents === null) {
                $this->importedContents = $object->importedContents;
            }
        }
    }
}
