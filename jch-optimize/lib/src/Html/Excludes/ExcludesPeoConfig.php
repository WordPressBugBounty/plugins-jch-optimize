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

namespace JchOptimize\Core\Html\Excludes;

/**
 * Excludes that preserve execution order (PEO).
 */
final class ExcludesPeoConfig
{
    /**
     * @param JsPeoRule[] $jsUrlRules
     * @param JsPeoRule[] $jsScriptRules
     * @param string[]    $cssUrls
     * @param string[]    $cssScriptPatterns
     */
    public function __construct(
        public array $jsUrlRules,
        public array $jsScriptRules,
        public array $cssUrls,
        public array $cssScriptPatterns
    ) {
    }

    /**
     * Build from the old raw array shape:
     *
     * [
     *   'js'        => list<array{url?:string,script?:string,ieo?:string,dontmove?:string}>,
     *   'css'       => string[],
     *   'js_script' => list<array{url?:string,script?:string,ieo?:string,dontmove?:string}>,
     *   'css_script'=> string[],
     * ]
     */
    public static function fromArray(array $raw): self
    {
        $js       = $raw['js']        ?? [];
        $jsScript = $raw['js_script'] ?? [];

        $jsUrlRules    = [];
        $jsScriptRules = [];

        foreach ($js as $r) {
            if (!is_array($r)) {
                continue;
            }

            $rule = JsPeoRule::fromArray($r);

            if ($rule->isUrlRule()) {
                $jsUrlRules[] = $rule;
            }
        }

        foreach ($jsScript as $r) {
            if (!is_array($r)) {
                continue;
            }

            $rule = JsPeoRule::fromArray($r);

            if ($rule->isScriptRule()) {
                $jsScriptRules[] = $rule;
            }
        }

        return new self(
            jsUrlRules: $jsUrlRules,
            jsScriptRules: $jsScriptRules,
            cssUrls: $raw['css'] ?? [],
            cssScriptPatterns: $raw['css_script'] ?? []
        );
    }

    /** Backwards-compat helpers: still return raw arrays if needed */

    /**
     * @return list<array{url?:string,script?:string,ieo?:string,dontmove?:string}>
     */
    public function rawJsArray(): array
    {
        return array_map(
            fn(JsPeoRule $r) => [
                'url'      => $r->url,
                'script'   => $r->script,
                'ieo'      => $r->ignoreExecutionOrder ? 'on' : null,
                'dontmove' => $r->dontMove ? 'on' : null,
            ],
            array_merge($this->jsUrlRules, $this->jsScriptRules)
        );
    }
}
