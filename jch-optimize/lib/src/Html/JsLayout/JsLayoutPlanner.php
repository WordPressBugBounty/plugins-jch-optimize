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

final class JsLayoutPlanner
{
    /**
     * @param JsItem[] $items in DOM order (ordinal ascending)
     */
    public function plan(array $items): JsPlacementPlan
    {
        $plan = new JsPlacementPlan();
        $gates = [];
        $lastPEOExcludeOrdinal = -1;

        // 1. Collect gates.
        foreach ($items as $item) {
            if ($item->isGate && !$item->isIeo) {
                $gates[] = $item;
            }
            if ($item->isExcluded && !$item->isIeo && $item->ordinal > $lastPEOExcludeOrdinal) {
                $lastPEOExcludeOrdinal = $item->ordinal;
            }
        }

        usort($gates, fn(JsItem $a, JsItem $b) => $a->ordinal <=> $b->ordinal);
        $plan->gates = $gates;

        // Helper: find first gate ordinal >= given ordinal.
        $findFirstGateAfter = function (int $ordinal) use ($gates): ?int {
            foreach ($gates as $gate) {
                if ($gate->ordinal >= $ordinal) {
                    return $gate->ordinal;
                }
            }

            return null;
        };

        foreach ($items as $item) {
            // Gates themselves are not part of the movable plan.
            if ($item->isGate) {
                continue;
            }

            $placement = new JsPlacementItem(
                isProcessed: $item->isProcessed,
                isDeferable: ($item->isProcessed || $item->isSensitive) && $item->ordinal > $lastPEOExcludeOrdinal,
                isDeferred: $item->isDeferred,
                isExcluded: $item->isExcluded,
                isSensitive: $item->isSensitive,
                groupIndex: $item->groupIndex,
                item: $item
            );

            $firstGateOrdinal = $findFirstGateAfter($item->ordinal);

            if (!$item->isIeo && $firstGateOrdinal !== null) {
                // Script "falls" as low as possible but cannot pass this gate.
                $plan->addBeforeGate($firstGateOrdinal, $placement);
            } else {
                // No gate below: falls to bottom of JS section.
                $plan->addToBottom($placement);
            }
        }

        // Preserve relative order inside each bucket.
        foreach ($plan->beforeGate as &$bucket) {
            usort(
                $bucket,
                fn(JsPlacementItem $a, JsPlacementItem $b) => $a->item->ordinal <=> $b->item->ordinal
            );
        }
        unset($bucket);

        usort(
            $plan->bottom,
            static function (JsPlacementItem $a, JsPlacementItem $b): int {
                // First: non-deferred (false) before deferred (true)
                $aDeferred = $a->item->isDeferred ? 1 : 0;
                $bDeferred = $b->item->isDeferred ? 1 : 0;

                if ($aDeferred !== $bDeferred) {
                    return $aDeferred <=> $bDeferred;
                }

                // Then: preserve original DOM order inside each subset
                return $a->item->ordinal <=> $b->item->ordinal;
            }
        );

        return $plan;
    }
}
