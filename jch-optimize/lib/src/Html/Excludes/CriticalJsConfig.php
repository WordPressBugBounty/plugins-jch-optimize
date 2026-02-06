<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2025 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Html\Excludes;

final class CriticalJsConfig
{
    /**
     * @param string[] $urls
     * @param string[] $scripts
     */
    public function __construct(
        public array $urls,
        public array $scripts
    ) {
    }

    public static function fromArray(array $raw): self
    {
        return new self(
            urls: $raw['js'] ?? [],
            scripts: $raw['script'] ?? []
        );
    }
}
