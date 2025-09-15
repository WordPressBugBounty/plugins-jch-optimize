<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2025 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Html\Callbacks;

use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Html\Elements\Script;
use JchOptimize\Core\Html\HtmlElementInterface;

class JavaScriptConfigureHelper extends CombineJsCss
{
    private array $scripts = [];

    private array $deferredScripts = [];

    protected function internalProcessMatches(HtmlElementInterface $element): string
    {
        if ($element instanceof Script) {
            $this->processJs($element);
        }

        return $element->render();
    }

    private function processJs(Script $script): void
    {
        $uri = $script->getSrc();
        $content = $script->getChildren()[0];

        if ($uri instanceof UriInterface) {
            if ($this->filesManager->isDuplicated($uri)) {
                return;
            }
            $needleIndex = 'url';
            $haystack = (string)$uri;
            $excludePeoIndex = 'js';
            $criticalJsIndex = 'js';
        } elseif (!empty(trim($content))) {
            $needleIndex = 'script';
            $haystack = $content;
            $excludePeoIndex = 'js_script';
            $criticalJsIndex = 'script';
        }

        if (isset($needleIndex) && isset($haystack) && isset($excludePeoIndex) && isset($criticalJsIndex)) {
            foreach ($this->excludes[$this->section]['excludes_peo'][$excludePeoIndex] as $exclude) {
                if (!empty($exclude[$needleIndex]) && Helper::findExcludes([$exclude[$needleIndex]], $haystack)) {
                    if (!isset($exclude['ieo'])) {
                        $this->scripts = [];
                    }
                    return;
                }
            }
            $criticalJsValue = $this->excludes[$this->section]['critical_js'][$criticalJsIndex];
            if (is_array($criticalJsValue) && Helper::findExcludes($criticalJsValue, (string)$uri)) {
                return;
            }

            $this->addToPotentialCriticalJs($script);
        }
    }

    public function getScripts(): array
    {
        return array_merge($this->scripts, $this->deferredScripts);
    }

    private function addToPotentialCriticalJs(Script $script): void
    {
        if (Helper::isScriptDeferred($script)) {
            $this->deferredScripts[] = $script;
        } else {
            $this->scripts[] = $script;
        }
    }
}
