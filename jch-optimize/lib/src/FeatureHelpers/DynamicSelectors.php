<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\FeatureHelpers;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Registry;

use function array_map;
use function array_merge;
use function array_unique;
use function defined;
use function implode;
use function preg_match;
use function preg_quote;

defined('_JCH_EXEC') or die('Restricted access');

class DynamicSelectors extends AbstractFeatureHelper
{
    protected string $dynamicSelectorRegex;

    public function __construct(Container $container, Registry $params)
    {
        parent::__construct($container, $params);

                //Add all CSS containing any specified dynamic CSS to the critical CSS
        $dynamicSelectors = Helper::getArray($this->params->get('pro_dynamic_selectors', []));
        $dynamicSelectors = array_map(
            fn($a) => preg_replace('#\x{200B}#u', '\b', preg_quote($a, '#')),
            array_unique(
                array_merge(
                    $dynamicSelectors,
                    ['offcanvas', 'off-canvas', 'mobilemenu', 'mobile-menu', '.jch-lazyloaded', ':active']
                )
            )
        );

        $this->dynamicSelectorRegex = implode('|', $dynamicSelectors);
    }

    public function getDynamicSelectors(string $selectorList): bool
    {
        if (preg_match('#' . $this->dynamicSelectorRegex . '#i', $selectorList)) {
            return true;
        }

        return false;
    }
}
