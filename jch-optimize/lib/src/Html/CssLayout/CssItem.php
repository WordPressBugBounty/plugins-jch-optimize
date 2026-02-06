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

use JchOptimize\Core\Html\Elements\Link;
use JchOptimize\Core\Html\Elements\Style;

final class CssItem
{
    public function __construct(
        public int $ordinal,        // 0,1,2,... in DOM order
        public ?int $groupIndex,     // FilesManager iIndex_css bucket
        public bool $isProcessed,   // true => in aCss group
        public bool $isExcluded,    // excluded via PEO/IEO/etc
        public bool $isInline,      // <style> vs <link>
        public bool $isMarker,
        public bool $isSensitive,
        public ?string $media,      // media attr, if any
        public Link|Style|null $node     // original element
    ) {
    }
}
