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
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareTrait;
use JchOptimize\Core\Registry;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

class AbstractFeatureHelper implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    protected Registry $params;

    public function __construct(Container $container, Registry $params)
    {
        $this->container = $container;
        $this->params = $params;
    }
}
