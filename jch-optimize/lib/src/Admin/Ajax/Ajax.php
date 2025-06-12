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

namespace JchOptimize\Core\Admin\Ajax;

use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareTrait;
use _JchOptimizeVendor\V91\Joomla\Input\Input;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareTrait;
use _JchOptimizeVendor\V91\Psr\Log\LoggerInterface;
use JchOptimize\Container\ContainerFactory;
use JchOptimize\Core\Admin\AdminHelper;
use JchOptimize\Core\Admin\Json;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\Platform\UtilityInterface;
use JchOptimize\Core\Registry;

use function defined;
use function error_reporting;
use function ini_set;
use function version_compare;

use const JCH_DEVELOP;

defined('_JCH_EXEC') or die('Restricted access');

abstract class Ajax implements ContainerAwareInterface, LoggerAwareInterface
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;

    /**
     * @var Input
     */
    protected Input $input;

    protected PathsInterface $paths;

    protected UtilityInterface $utility;

    protected AdminHelper $adminHelper;

    protected Registry $params;

    protected function __construct()
    {
        ini_set('pcre.backtrack_limit', '1000000');
        ini_set('pcre.recursion_limit', '1000000');

        if (!JCH_DEVELOP) {
            error_reporting(0);
            @ini_set('display_errors', 'Off');
        }

        if (version_compare(PHP_VERSION, '7.0.0', '>=')) {
            ini_set('pcre.jit', '0');
        }

        $this->container = ContainerFactory::create();
        $this->logger = $this->container->get(LoggerInterface::class);
        $this->input = $this->container->get(Input::class);
        $this->paths = $this->container->get(PathsInterface::class);
        $this->utility = $this->container->get(UtilityInterface::class);
        $this->adminHelper = $this->container->get(AdminHelper::class);
        $this->params = $this->container->get(Registry::class);
    }

    public static function getInstance(string $sClass): Ajax
    {
        $sFullClass = '\\JchOptimize\\Core\\Admin\\Ajax\\' . $sClass;

        /** @var Ajax */
        return new $sFullClass();
    }

    /**
     * @return Json|string|void
     */
    abstract public function run();
}
