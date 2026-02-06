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

final class RemoveConfig
{
    /**
     * @param string[] $js
     * @param string[] $css
     */
    public function __construct(
        public array $js,
        public array $css
    ) {
    }

    public static function fromArray(array $raw): self
    {
        return new self(
            js: $raw['js'] ?? [],
            css: $raw['css'] ?? []
        );
    }
}
