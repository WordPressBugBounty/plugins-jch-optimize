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

namespace JchOptimize\Core\Css\Callbacks\Dependencies;

use DOMAttr;
use DOMDocument;
use DOMXPath;
use JchOptimize\Core\Exception\PregErrorException;
use JchOptimize\Core\Html\HtmlProcessor;

use function libxml_clear_errors;
use function libxml_use_internal_errors;

class CriticalCssDependencies
{
    private DOMXPath $DOMXPath;

    private DOMDocument $dom;

    private ?CriticalCssDomainProfiler $profiler = null;

    private string $criticalCssAggregate = '';

    private string $dynamicCriticalCssAggregate = '';

    private string $potentialCriticalCssAtRules = '';

    private string $htmlAboveFold = '';

    public array $selectorListCache = [];

    public array $selectorCache = [];

    public array $criticalTypeBySelectorListCache = [];

    // indexes
    private array $tagNames = [];           // ['div' => true, 'header' => true]
    private array $ids = [];           // ['main-header' => true]
    private array $classes = [];           // ['btn-primary' => true]
    private array $attrs = [];           // ['data-foo' => ['bar' => true, '*' => true]]


    public function __construct(HtmlProcessor $processor)
    {
        $loadedDOMDocument = $this->loadHtmlInDom($processor);

        $this->DOMXPath = new DOMXPath($loadedDOMDocument);
    }

    public function getDOMXPath(): DOMXPath
    {
        return $this->DOMXPath;
    }

    private function loadHtmlInDom(HtmlProcessor $processor): DOMDocument
    {
        try {
            $html = $processor->removeScriptsFromHtml($processor->getBodyHtml());
        } catch (PregErrorException $e) {
            $html = '';
        }
        $this->htmlAboveFold = <<<HTML
<html>
<head>
<title></title>
</head>
 {$processor->getAboveFoldHtml($html)}
 </body>
</html>
HTML;

        $oDom = new DOMDocument();

        //Load HTML in DOM
        $prev = libxml_use_internal_errors(true);
        $oDom->loadHtml($this->htmlAboveFold);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $this->dom = $oDom;
        $this->buildIndexes($oDom);

        return $oDom;
    }

    private function buildIndexes(DOMDocument $dom): void
    {
        $all = $dom->getElementsByTagName('*');

        foreach ($all as $el) {
            $tag = strtolower($el->tagName);
            $this->tagNames[$tag] = true;

            if ($el->hasAttribute('id')) {
                $this->ids[$el->getAttribute('id')] = true;
            }

            if ($el->hasAttribute('class')) {
                $classes = preg_split('/\s+/', $el->getAttribute('class'), -1, PREG_SPLIT_NO_EMPTY);
                foreach ($classes as $cls) {
                    $this->classes[$cls] = true;
                }
            }

            if ($el->hasAttributes()) {
                foreach ($el->attributes as $attr) {
                    /** @var DOMAttr $attr */
                    $name = strtolower($attr->name);
                    $value = $attr->value;

                    $this->attrs[$name] ??= [];
                    $this->attrs[$name][$value] = true;
                    // Also record existence-only
                    $this->attrs[$name]['*'] = true;
                }
            }
        }
    }

    // Small helpers:
    public function hasTag(string $tag): bool
    {
        return isset($this->tagNames[strtolower($tag)]);
    }

    public function hasId(string $id): bool
    {
        return isset($this->ids[$id]);
    }

    public function hasClass(string $class): bool
    {
        return isset($this->classes[$class]);
    }

    public function hasAttrName(string $name): bool
    {
        return isset($this->attrs[strtolower($name)]);
    }

    public function hasAttrValue(string $name, string $value): bool
    {
        $name = strtolower($name);

        if (isset($this->attrs[$name]['*'])) {
            foreach ($this->attrs[$name] as $attrValue => $bool) {
                if (str_contains($attrValue, $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getHtmlKey(): string
    {
        $tags = array_keys($this->tagNames);
        sort($tags);

        $classes = array_keys($this->classes);
        sort($classes);

        $attrNames = array_keys($this->attrs);
        sort($attrNames);

        $ids = array_keys($this->ids);
        sort($ids);

        $summary = [
            'tags:' . implode(',', $tags),
            'classes:' . implode(',', $classes),
            'attrs:' . implode(',', $attrNames),
            'ids:' . implode(',', $ids),
        ];

        $signature = implode('|', $summary);

        return md5($signature);
    }

    public function getCriticalCssAggregate(): string
    {
        return $this->criticalCssAggregate;
    }

    public function getDynamicCriticalCssAggregate(): string
    {
        return $this->dynamicCriticalCssAggregate;
    }

    public function getPotentialCriticalCssAtRules(): string
    {
        return $this->potentialCriticalCssAtRules;
    }

    public function addToCriticalCssAggregate(string $cssAggregate): static
    {
        $this->criticalCssAggregate .= $cssAggregate;

        return $this;
    }

    public function addToDynamicCriticalCssAggregate(string $cssAggregate): static
    {
        $this->dynamicCriticalCssAggregate .= $cssAggregate;

        return $this;
    }

    public function addToPotentialCriticalCssAtRules(string $cssAtRule): static
    {
        $this->potentialCriticalCssAtRules .= $cssAtRule;

        return $this;
    }

    public function getHtmlAboveFold(): string
    {
        return $this->htmlAboveFold;
    }

    public function reset(): void
    {
        $this->criticalCssAggregate = '';
        $this->dynamicCriticalCssAggregate = '';
        $this->potentialCriticalCssAtRules = '';
    }

    public function getProfiler(): ?CriticalCssDomainProfiler
    {
        return $this->profiler;
    }

    public function setProfiler(?CriticalCssDomainProfiler $profiler): static
    {
        $this->profiler = $profiler;

        return $this;
    }
}
