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

namespace JchOptimize\Core\Admin\Ajax;

use _JchOptimizeVendor\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\Joomla\Input\Input;
use _JchOptimizeVendor\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareTrait;
use _JchOptimizeVendor\Psr\Log\LoggerInterface;
use JchOptimize\ContainerFactory;
use JchOptimize\Core\Admin\Json;
use JchOptimize\Core\Container\ContainerAwareTrait;

\defined('_JCH_EXEC') or exit('Restricted access');
abstract class Ajax implements ContainerAwareInterface, LoggerAwareInterface
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;

    protected Input $input;

    protected function __construct()
    {
        \ini_set('pcre.backtrack_limit', '1000000');
        \ini_set('pcre.recursion_limit', '1000000');
        if (!\JCH_DEVELOP) {
            \error_reporting(0);
            @\ini_set('display_errors', 'Off');
        }
        if (\version_compare(\PHP_VERSION, '7.0.0', '>=')) {
            \ini_set('pcre.jit', '0');
        }
        $this->container = ContainerFactory::getContainer();
        $this->logger = $this->container->get(LoggerInterface::class);
        $this->input = $this->container->get(Input::class);
    }

    public static function getInstance(string $sClass): Ajax
    {
        $sFullClass = '\\JchOptimize\\Core\\Admin\\Ajax\\'.$sClass;
        // @var Ajax
        return new $sFullClass();
    }

    /**
     * @return Json|string|void
     */
    abstract public function run();
}
