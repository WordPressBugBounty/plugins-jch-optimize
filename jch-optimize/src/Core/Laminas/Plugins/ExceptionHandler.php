<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Laminas\Plugins;

use Exception;
use JchOptimize\ContainerFactory;
use Psr\Log\LoggerInterface;

class ExceptionHandler
{
    public static function logException(Exception $e): void
    {
        $container = ContainerFactory::getContainer();
        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        $logger->error((string)$e);
    }
}
