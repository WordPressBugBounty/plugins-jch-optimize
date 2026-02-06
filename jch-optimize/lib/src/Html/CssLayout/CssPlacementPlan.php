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

final class CssPlacementPlan
{
    /**
     * CSS that should remain in head at (roughly) original positions.
     * (used when optimizeCssDelivery_enable = 0)
     *
     * @var CssPlacementItem[]
     */
    public array $headBlocking = [];

    /**
     * CSS that should be loaded asynchronously from body
     * (preload+onload or dynamic loader).
     *
     * @var CssPlacementItem[]
     */
    public array $bodyAsync = [];

    /**
     * CSS that should be inlined as critical.
     *
     * @var CssPlacementItem[]
     */
    public array $headInlineCritical = [];

    /**
     * Dynamic critical CSS inlined in the body
     *
     * @var CssPlacementItem[];
     */
    public array $bodyInlineDynamicCritical = [];

    /**
     * Sensitive items that should be loaded dynamically
     *
     * @var CssPlacementItem[];
     */
    public array $bodySensitiveDynamic = [];

    /**
     * Whether we should append a “below the fold fonts” CSS block in the body.
     * The actual element is created in CacheManager and passed to HtmlManager.
     */
    public bool $appendBelowFoldFonts = false;

    /**
     * Whether we should append the “reduced unused CSS” combined bundle in the body.
     * The actual element is created in CacheManager and passed to HtmlManager.
     */
    public bool $appendReducedUnusedBundle = false;
}
