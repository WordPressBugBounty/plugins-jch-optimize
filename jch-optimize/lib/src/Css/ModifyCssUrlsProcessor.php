<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Css;

use JchOptimize\Core\Css\Components\CssUrl;

interface ModifyCssUrlsProcessor
{
    public function processCssUrls(CssUrl $cssUrl): ?CssUrl;

    public function postProcessModifiedCssComponent(CssComponents $cssComponent);
}
