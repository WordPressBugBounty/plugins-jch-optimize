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

use JchOptimize\Core\Html\Elements\Script;

final class JsItem
{
    public function __construct(
        public int $ordinal,      // 0,1,2,... in DOM order
        public ?int $groupIndex,   // FilesManager iIndex_js / bucket
        public bool $isProcessed, // true => goes through Combiner
        public bool $isExcluded,  // true => NOT processed
        public bool $isGate,      // true => excluded + dontmove
        public bool $isIeo,       // ignore execution order (IEO)
        public bool $isDeferred,  // deferred/module etc
        public bool $isSensitive,
        public ?Script $node       // HTML element for this script
    ) {
    }
}
