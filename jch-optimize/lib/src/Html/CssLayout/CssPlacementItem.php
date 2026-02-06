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

namespace JchOptimize\Core\Html\CssLayout;

final class CssPlacementItem
{
    public function __construct(
        public bool $isProcessed,
        public bool $isMarker,
        public ?int $groupIndex,     // group index in aCss
        public bool $isSensitive,
        public ?CssItem $item     // original element (for inline/excluded cases)
    ) {
    }
}
