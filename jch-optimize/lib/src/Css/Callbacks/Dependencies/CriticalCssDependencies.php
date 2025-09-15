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

use DOMDocument;
use DOMXPath;
use JchOptimize\Core\Exception\PregErrorException;
use JchOptimize\Core\Html\HtmlProcessor;

use function libxml_clear_errors;
use function libxml_use_internal_errors;

class CriticalCssDependencies
{
    private DOMXPath $DOMXPath;

    private string $criticalCssAggregate = '';

    private string $potentialCriticalCssAtRules = '';

    private string $htmlAboveFold = '';

    public array $selectorListCache = [];

    public array $selectorCache = [];


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
        $this->htmlAboveFold = strtolower($this->htmlAboveFold);

        $oDom = new DOMDocument();

        //Load HTML in DOM
        libxml_use_internal_errors(true);
        $oDom->loadHtml($this->htmlAboveFold);
        libxml_clear_errors();

        return $oDom;
    }

    public function getCriticalCssAggregate(): string
    {
        return $this->criticalCssAggregate;
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
        $this->potentialCriticalCssAtRules = '';
    }
}
