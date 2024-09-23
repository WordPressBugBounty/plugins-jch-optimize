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

namespace JchOptimize\Core;

use JchOptimize\ContainerFactory;
use Psr\Log\LoggerInterface;

use function defined;
use function error_get_last;
use function ob_get_clean;
use function register_shutdown_function;
use function set_error_handler;
use function var_dump;

use const E_ALL;

defined('_JCH_EXEC') or die('Restricted access');

abstract class Debugger
{
    private static bool $dieOnError = false;

    public static function printr($var, $label = null, $condition = true): void
    {
        if ($condition) {
            self::debug('printr', $var, $label);
        }
    }

    /**
     * @param $method
     * @param $var
     * @param $label
     * @return void
     * @psalm-suppress ForbiddenCode
     */
    private static function debug(string $method, $var, $label = null): void
    {
        /** @var LoggerInterface $logger */
        $logger = ContainerFactory::getContainer()->get(LoggerInterface::class);

        if (is_null($label)) {
            $name = '';
        } else {
            $name = $label . ': ';
        }

        switch ($method) {
            case 'vdump':
                ob_start();
                var_dump($var);
                $logger->debug($name . ob_get_clean());

                break;
            case 'printr':
            default:
                $logger->debug($name . print_r($var, true));

                break;
        }
    }

    public static function vdump($var, $label = null, $condition = true): void
    {
        if ($condition) {
            self::debug('vdump', $var, $label);
        }
    }

    public static function attachErrorHandler(bool $dieOnError = false): void
    {
        self::$dieOnError = $dieOnError;

        set_error_handler([Debugger::class, 'debuggerErrorHandler'], E_ALL);
        register_shutdown_function([Debugger::class, 'debuggerCatchFatalErrors']);
    }

    /**
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @return void
     */
    public static function debuggerErrorHandler(int $errno, string $errstr, string $errfile, int $errline): void
    {
        /** @var LoggerInterface $logger */
        $logger = ContainerFactory::getContainer()->get(LoggerInterface::class);

        $msg = 'Error no: ' . $errno . ', Message: ' . $errstr . ' in file: ' . $errfile . ' at line: ' . $errline . "\n";

        $logger->error($msg);

        if (self::$dieOnError) {
            die();
        }
    }

    public static function debuggerCatchFatalErrors(): void
    {
        /** @var LoggerInterface $logger */
        $logger = ContainerFactory::getContainer()->get(LoggerInterface::class);

        $error = error_get_last();
        $msg = 'Error type: ' . $error['type'] . ', Message: ' . $error['message'] . ' in file: ' . $error['file'] . ' at line: ' . $error['line'] . "\n";

        $logger->error($msg);
    }
}
