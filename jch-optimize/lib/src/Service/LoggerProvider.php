<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads.
 *
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Service;

use _JchOptimizeVendor\Joomla\DI\Container;
use _JchOptimizeVendor\Joomla\DI\ServiceProviderInterface;
use _JchOptimizeVendor\Laminas\Log\Logger;
use _JchOptimizeVendor\Laminas\Log\Processor\Backtrace;
use _JchOptimizeVendor\Laminas\Log\PsrLoggerAdapter;
use _JchOptimizeVendor\Laminas\Log\Writer\Stream;
use _JchOptimizeVendor\Psr\Log\LoggerInterface;
use JchOptimize\Platform\Paths;

class LoggerProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container->share('logger', function (Container $container): LoggerInterface {
            $logger = new Logger();
            $writer = new Stream(Paths::getLogsPath().'/jch-optimize.log');
            $logger->addWriter($writer);
            $logger->addProcessor(new Backtrace(['ignoredNamespaces' => ['_JchOptimizeVendor\\Psr\\Log\\AbstractLogger']]));

            return new PsrLoggerAdapter($logger);
        });
    }
}
