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

namespace JchOptimize\Core\Html\Callbacks;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use JchOptimize\Core\FeatureHelpers\DynamicJs;
use JchOptimize\Core\Html\Elements\Script;
use JchOptimize\Core\Html\FilesManager;
use JchOptimize\Core\Html\HtmlProcessor;
use JchOptimize\Core\Html\JsLayout\JsLayoutPlanner;
use JchOptimize\Core\Platform\ExcludesInterface;
use JchOptimize\Core\Platform\ProfilerInterface;
use JchOptimize\Core\Registry;

class JavaScriptConfigureHelper extends CombineJsCss
{
    public function __construct(
        Container $container,
        Registry $params,
        FilesManager $filesManager,
        HtmlProcessor $htmlProcessor,
        ProfilerInterface $profiler,
        ExcludesInterface $platformExcludes,
        private JsLayoutPlanner $planner,
        private DynamicJs $dynamicJs
    ) {
        parent::__construct($container, $params, $filesManager, $htmlProcessor, $profiler, $platformExcludes);
    }

    public function getScripts(): array
    {
        $plan = $this->planner->plan($this->filesManager->jsTimeLine);
        $scripts = [];

        foreach ($plan->bottom as $placement) {
            if (
                ($placement->isProcessed && $placement->isDeferable)
                || ($placement->item->isDeferred && !$placement->item->isExcluded)
            ) {
                $script = $placement->item->node;

                if ($script instanceof Script && !$this->dynamicJs->isCritical($script)) {
                    $scripts[] = $script;
                }
            }
        }

        return $scripts;
    }
}
