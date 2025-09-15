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

use _JchOptimizeVendor\V91\Psr\Container\ContainerInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerInterface;
use JchOptimize\Container\ContainerFactory;
use JchOptimize\Core\Platform\PathsInterface;

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
    private static ContainerInterface $container;
    private static bool $dieOnError = false;

    public static function printr(mixed $var, ?string $label = null, bool $condition = true): void
    {
        if ($condition) {
            self::debug('printr', $var, $label);
        }
    }

    private static function debug(string $method, mixed $var, ?string $label = null): void
    {
        if (!isset(self::$container) || !self::$container->has(LoggerInterface::class)) {
            return;
        }

        /** @var LoggerInterface $logger */
        /** @noinspection PhpUnhandledExceptionInspection */
        $logger = self::$container->get(LoggerInterface::class);

        if (is_null($label)) {
            $name = '';
        } else {
            $name = $label . ': ';
        }

        switch ($method) {
            case 'vdump':
                ob_start();
                /** @psalm-suppress ForbiddenCode */
                var_dump($var);
                $logger->debug($name . ob_get_clean());

                break;
            case 'printr':
            default:
                $logger->debug($name . print_r($var, true));

                break;
        }
    }

    public static function vdump(mixed $var, ?string $label = null, bool $condition = true): void
    {
        if ($condition) {
            self::debug('vdump', $var, $label);
        }
    }

    public static function attachErrorHandler(bool $dieOnError = false): void
    {
        self::$dieOnError = $dieOnError;

        set_error_handler([Debugger::class, 'debuggerErrorHandler'], E_ALL);
        register_shutdown_function([Debugger::class, 'debuggerCatchLastErrors']);
    }

    public static function debuggerErrorHandler(
        int $errno,
        string $errstr,
        string $errfile = '',
        int $errline = 0
    ): bool {
        if (!isset(self::$container) || !self::$container->has(LoggerInterface::class)) {
            return false;
        }

        /** @var LoggerInterface $logger */
        /** @noinspection PhpUnhandledExceptionInspection */
        $logger = self::$container->get(LoggerInterface::class);

        $msg = 'Error no: ' . $errno
            . ', Message: ' . $errstr
            . ' in file: ' . $errfile
            . ' at line: ' . $errline . "\n";

        $logger->error($msg);

        return !self::$dieOnError;
    }

    public static function debuggerCatchLastErrors(): void
    {
        if (!isset(self::$container) || !self::$container->has(LoggerInterface::class)) {
            return;
        }

        $error = error_get_last();

        if ($error !== null) {
            $msg = 'Error type: ' . $error['type']
                . ', Message: ' . $error['message']
                . ' in file: ' . $error['file']
                . ' at line: ' . $error['line'] . "\n";

            /** @var LoggerInterface $logger */
            /** @noinspection PhpUnhandledExceptionInspection */
            $logger = self::$container->get(LoggerInterface::class);
            $logger->error($msg);
        }
    }

    public static function setErrorLogging(int $error_level = E_ALL): void
    {
        error_reporting($error_level);
        @ini_set('log_errors', 'On');
        @ini_set(
            'error_log',
            ContainerFactory::getInstance()->get(PathsInterface::class)->getLogsPath() . '/php-errors.log'
        );
        @ini_set('display_errors', 'Off');
    }

    public static function setContainer(ContainerInterface $container): void
    {
        self::$container = $container;
    }
}
