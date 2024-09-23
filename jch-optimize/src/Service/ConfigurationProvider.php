<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2021 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Service;

use JchOptimize\Core\Container\Container;
use JchOptimize\Core\Container\ServiceProviderInterface;
use JchOptimize\Core\Registry;

use function define;
use function defined;

class ConfigurationProvider implements ServiceProviderInterface
{
    /**
     * @var Registry
     */
    private Registry $params;

    public function __construct()
    {
        $this->params = new Registry(get_option('jch-optimize_settings'));

        if (!defined('JCH_DEBUG')) {
            define('JCH_DEBUG', ($this->params->get('debug', 0)));
        }
    }

    public function register(Container $container)
    {
        $container->alias('params', Registry::class)
                  ->share(
                      Registry::class,
                      function (): Registry {
                          return $this->params;
                      },
                  );
    }
}
