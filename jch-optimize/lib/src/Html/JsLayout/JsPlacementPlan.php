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

final class JsPlacementPlan
{
    /**
     * @var array<int, JsPlacementItem[]>  // gateOrdinal => items before that gate
     */
    public array $beforeGate = [];

    /**
     * @var JsPlacementItem[]  // items that fall to bottom of JS section
     */
    public array $bottom = [];

    /**
     * @var JsItem[] gates in DOM order
     */
    public array $gates = [];

    public function addBeforeGate(int $gateOrdinal, JsPlacementItem $placement): void
    {
        $this->beforeGate[$gateOrdinal][] = $placement;
    }

    public function addToBottom(JsPlacementItem $placement): void
    {
        $this->bottom[] = $placement;
    }
}
