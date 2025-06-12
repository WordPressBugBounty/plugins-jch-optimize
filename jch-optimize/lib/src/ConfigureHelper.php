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

namespace JchOptimize\Core;

use JchOptimize\Core\Html\HtmlElementBuilder;
use JchOptimize\Core\Html\HtmlManager;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Uri\Utils;

use const JCH_DEBUG;
use const JCH_VERSION;

class ConfigureHelper
{
    public function __construct(
        private Registry $params,
        private HtmlManager $htmlManager,
        private PathsInterface $pathsUtils
    ) {
    }

    public function loadNumElementsScript(): void
    {
        if (JCH_DEBUG && $this->params->get('elements_above_fold_marker', '0')) {
            $script = HtmlElementBuilder::script();
            $script->src(
                Utils::uriFor(
                    $this->pathsUtils->mediaUrl() . '/configure-helpers/num_elements.js?' . JCH_VERSION,
                )
            )->defer();
            $this->htmlManager->appendChildToHTML((string)$script, 'body');
        }
    }

    public function loadDynamicCssElementsScript(): void
    {
        if (JCH_DEBUG && $this->params->get('critical_css_configure_helper', '0')) {
            $script = HtmlElementBuilder::script();
            $script->src(
                Utils::uriFor(
                    $this->pathsUtils->mediaUrl() . '/configure-helpers/dynamic_css_elements.js?' . JCH_VERSION
                )
            );
            $this->htmlManager->appendChildToHTML((string)$script, 'body');
        }
    }
}
