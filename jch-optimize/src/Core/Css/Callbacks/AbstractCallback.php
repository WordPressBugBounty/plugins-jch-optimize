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

namespace JchOptimize\Core\Css\Callbacks;

use JchOptimize\Core\Container\Container;
use Joomla\DI\ContainerAwareInterface;
use Joomla\DI\ContainerAwareTrait;
use Joomla\Registry\Registry;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

abstract class AbstractCallback implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @var Registry
     */
    protected Registry $params;

    public function __construct(Container $container, Registry $params)
    {
        $this->container = $container;
        $this->params = $params;
    }

    /**
     * @param string[] $matches
     * @param string $context
     * @return string
     */
    abstract public function processMatches(array $matches, string $context): string;
}
