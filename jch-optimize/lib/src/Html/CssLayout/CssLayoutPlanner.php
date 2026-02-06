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

final class CssLayoutPlanner
{
    /**
     * @param CssItem[] $items // cssTimeline
     * @param bool $optimizeDelivery // optimizeCssDelivery_enable == '1'
     * @param bool $reduceUnusedCss // pro_reduce_unused_css
     */
    public function plan(array $items, bool $optimizeDelivery, bool $reduceUnusedCss): CssPlacementPlan
    {
        $plan = new CssPlacementPlan();

        // We don't need gates here (for now). Order is purely ordinal.
        usort($items, fn(CssItem $a, CssItem $b) => $a->ordinal <=> $b->ordinal);

        foreach ($items as $item) {
            // From this point we know it's a processed group (aCss bucket).
            $placement = new CssPlacementItem(
                isProcessed: $item->isProcessed,
                isMarker: $item->isMarker,
                groupIndex: $item->groupIndex,
                isSensitive: $item->isSensitive,
                item: $item,
            );

            // 1) Head-blocking list
            // IEO / removed CSS is handled by FilesManager and never enters the plan.
            // Excluded (non-processed) CSS still participates in "headBlocking" layout.
            if ((!$optimizeDelivery && !$reduceUnusedCss) || $item->isExcluded) {
                $plan->headBlocking[] = $placement;

                continue;
            }

            // 2) Sensitive CSS handling
            if ($item->isSensitive) {
                // Optimize OFF: they just behave as head-blocking CSS already (above).
                if ($optimizeDelivery) {
                    // Optimize Delivery ON:
                    // - use them for critical & dynamic-critical
                    $plan->headInlineCritical[] = $placement;
                    $plan->bodyInlineDynamicCritical[] = $placement;

                    // Reduce-unused OFF: also load the original file asynchronously.
                    if (!$reduceUnusedCss) {
                        $plan->bodyAsync[] = $placement;
                    } else {
                        $plan->bodySensitiveDynamic[] = $placement;
                    }
                }

                // Don't fall through into "processed group" logic
                continue;
            }

            // 3) Normal (non-sensitive) CSS handling
            if ($optimizeDelivery && !$reduceUnusedCss && $item->isProcessed) {
                // Optimize CSS Delivery ON, Reduce Unused OFF:
                // - critical CSS in head
                // - dynamic critical CSS in body
                // - full CSS loaded async from body
                $plan->bodyAsync[] = $placement;
                $plan->headInlineCritical[] = $placement;
                $plan->bodyInlineDynamicCritical[] = $placement;
            } elseif ($optimizeDelivery && $reduceUnusedCss && $item->isProcessed) {
                // Optimize CSS Delivery ON, Reduce Unused ON:
                // - critical CSS in head
                // - dynamic critical CSS in body
                // - async CSS comes from a *single* combined bundle (not per-group)
                $plan->headInlineCritical[] = $placement;
                $plan->bodyInlineDynamicCritical[] = $placement;
                // NOTE: no per-group bodyAsync entries here.
            }
        }

        // Below-the-fold fonts CSS:
        // Only relevant when optimizeDelivery is ON and reduceUnusedCss is OFF.
        if ($optimizeDelivery && !$reduceUnusedCss) {
            $plan->appendBelowFoldFonts = true;
        }

        // Reduced unused CSS bundle:
        // Only relevant when reduceUnusedCss is ON.
        if ($reduceUnusedCss) {
            $plan->appendReducedUnusedBundle = true;
        }

        return $plan;
    }
}
