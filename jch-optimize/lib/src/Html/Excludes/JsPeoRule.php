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

final class JsPeoRule
{
    public function __construct(
        public ?string $url,      // pattern matched against src/href
        public ?string $script,   // pattern matched against inline content
        public bool $ignoreExecutionOrder, // ieo
        public bool $dontMove     // dontmove
    ) {
    }

    public static function fromArray(array $raw): self
    {
        $url    = $raw['url']    ?? null;
        $script = $raw['script'] ?? null;

        return new self(
            url: $url,
            script: $script,
            ignoreExecutionOrder: !empty($raw['ieo']),
            dontMove: !empty($raw['dontmove'])
        );
    }

    public function isUrlRule(): bool
    {
        return $this->url !== null;
    }

    public function isScriptRule(): bool
    {
        return $this->script !== null;
    }
}
