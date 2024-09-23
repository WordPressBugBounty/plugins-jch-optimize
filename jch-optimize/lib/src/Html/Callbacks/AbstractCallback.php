<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads.
 *
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Html\Callbacks;

use _JchOptimizeVendor\Joomla\DI\Container;
use _JchOptimizeVendor\Joomla\DI\ContainerAwareInterface;
use JchOptimize\Core\Container\ContainerAwareTrait;
use JchOptimize\Core\Html\CallbackInterface;
use JchOptimize\Core\Registry;

\defined('_JCH_EXEC') or exit('Restricted access');
abstract class AbstractCallback implements ContainerAwareInterface, CallbackInterface
{
    use ContainerAwareTrait;

    /**
     * @var string RegEx used to process HTML
     */
    protected string $regex = '';

    /**
     * @var Registry Plugin parameters
     */
    protected Registry $params;

    public function __construct(Container $container, Registry $params)
    {
        $this->container = $container;
        $this->params = $params;
    }

    public function setRegex(string $regex): void
    {
        $this->regex = $regex;
    }

    /**
     * @param string[] $matches
     */
    abstract public function processMatches(array $matches): string;
}
