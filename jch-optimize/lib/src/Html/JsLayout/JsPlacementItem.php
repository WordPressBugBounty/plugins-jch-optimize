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

namespace JchOptimize\Core\Html\JsLayout;

final class JsPlacementItem
{
    public function __construct(
        public bool $isProcessed,
        public bool $isDeferable, //Is processed and below last excluded
        public bool $isDeferred,
        public bool $isExcluded,
        public bool $isSensitive,
        public ?int $groupIndex, //For processed items
        public JsItem $item
    ) {
    }
}

