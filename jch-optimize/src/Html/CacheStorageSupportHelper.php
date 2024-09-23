<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Html;

use function class_exists;
use function extension_loaded;
use function function_exists;
use function ini_get;
use function strcmp;

abstract class CacheStorageSupportHelper
{
    /**
     * Test to see if the  APCU storage handler is available.
     *
     * @return  bool
     */
    public static function isApcuSupported(): bool
    {
        $supported = extension_loaded('apcu') && ini_get('apc.enabled');

        // If on the CLI interface, the `apc.enable_cli` option must also be enabled
        if ($supported && PHP_SAPI === 'cli') {
            $supported = ini_get('apc.enable_cli');
        }

        return (bool)$supported;
    }

    /**
     * Test to see if the Memcached storage handler is available.
     *
     * @return  bool
     */
    public static function isMemcachedSupported(): bool
    {
        /*
         * GAE and HHVM have both had instances where Memcached the class was defined but no extension was loaded.
         * If the class is there, we can assume support.
         */
        return class_exists('Memcached');
    }

    /**
     * Test to see if the Redis storage handler is available.
     *
     * @return  bool
     */
    public static function isRedisSupported(): bool
    {
        return class_exists('\\Redis');
    }

    /**
     * Test to see if the Wincache storage handler is available.
     *
     * @return  bool
     */
    public static function isWincacheSupported(): bool
    {
        return extension_loaded('wincache') && function_exists('wincache_ucache_get') && !strcmp(
            ini_get('wincache.ucenabled'),
            '1'
        );
    }
}
