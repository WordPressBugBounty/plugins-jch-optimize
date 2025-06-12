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

namespace JchOptimize\Core\Service\Provider;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Joomla\DI\ServiceProviderInterface;
use _JchOptimizeVendor\V91\Monolog\Formatter\LineFormatter;
use _JchOptimizeVendor\V91\Monolog\Handler\StreamHandler;
use _JchOptimizeVendor\V91\Monolog\Logger;
use _JchOptimizeVendor\V91\Psr\Log\LoggerInterface;
use JchOptimize\Core\Platform\PathsInterface;

class PsrLogger implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->share(LoggerInterface::class, function (Container $container): LoggerInterface {
            $logsPath = $container->get(PathsInterface::class)->getLogsPath() . '/jch-optimize.log';

            $logger = new Logger('File');
            $streamHandler = new StreamHandler($logsPath);
            $formatter = (new LineFormatter())
                ->allowInlineLineBreaks()
                ->ignoreEmptyContextAndExtra();
            $streamHandler->setFormatter($formatter);
            $logger->pushHandler($streamHandler);

            return $logger;
        });
    }
}
