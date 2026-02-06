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

final class SectionExcludes
{
    public function __construct(
        public ExcludesPeoConfig $peo,
        public CriticalJsConfig $criticalJs,
        public RemoveConfig $remove
    ) {
    }

    /**
     * Build from the old $aExcludes shape:
     *
     * [
     *   'excludes_peo' => [...],
     *   'critical_js'  => [...],
     *   'remove'       => [...],
     * ]
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            peo: ExcludesPeoConfig::fromArray($raw['excludes_peo'] ?? []),
            criticalJs: CriticalJsConfig::fromArray($raw['critical_js'] ?? []),
            remove: RemoveConfig::fromArray($raw['remove'] ?? [])
        );
    }
}
