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

namespace JchOptimize\Core;

use _JchOptimizeVendor\Psr\Log\LoggerInterface;
use JchOptimize\ContainerFactory;
use JchOptimize\Platform\Paths;

\defined('_JCH_EXEC') or exit('Restricted access');
abstract class Debugger
{
    private static bool $dieOnError = \false;

    public static function printr($var, $label = null, $condition = \true): void
    {
        if ($condition) {
            self::debug('printr', $var, $label);
        }
    }

    public static function vdump($var, $label = null, $condition = \true): void
    {
        if ($condition) {
            self::debug('vdump', $var, $label);
        }
    }

    public static function attachErrorHandler(bool $dieOnError = \false): void
    {
        self::$dieOnError = $dieOnError;
        \set_error_handler([\JchOptimize\Core\Debugger::class, 'debuggerErrorHandler'], \E_ALL);
        \register_shutdown_function([\JchOptimize\Core\Debugger::class, 'debuggerCatchFatalErrors']);
    }

    public static function debuggerErrorHandler(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
    {
        /** @var LoggerInterface $logger */
        $logger = ContainerFactory::getContainer()->get(LoggerInterface::class);
        $msg = 'Error no: '.$errno.', Message: '.$errstr.' in file: '.$errfile.' at line: '.$errline."\n";
        $logger->error($msg);

        return \true;
    }

    public static function debuggerCatchFatalErrors(): void
    {
        /** @var LoggerInterface $logger */
        $logger = ContainerFactory::getContainer()->get(LoggerInterface::class);
        $error = \error_get_last();
        $msg = 'Error type: '.$error['type'].', Message: '.$error['message'].' in file: '.$error['file'].' at line: '.$error['line']."\n";
        $logger->error($msg);
    }

    public static function setErrorLogging(?int $error_level = \E_ALL): void
    {
        \error_reporting($error_level);
        @\ini_set('log_errors', 'On');
        @\ini_set('error_log', Paths::getLogsPath().'/php-errors.log');
        @\ini_set('display_errors', 'Off');
    }

    /**
     * @psalm-suppress ForbiddenCode
     */
    private static function debug(string $method, $var, $label = null): void
    {
        /** @var LoggerInterface $logger */
        $logger = ContainerFactory::getContainer()->get(LoggerInterface::class);
        if (\is_null($label)) {
            $name = '';
        } else {
            $name = $label.': ';
        }

        switch ($method) {
            case 'vdump':
                \ob_start();
                \var_dump($var);
                $logger->debug($name.\ob_get_clean());

                break;

            case 'printr':
            default:
                $logger->debug($name.\print_r($var, \true));

                break;
        }
    }
}
