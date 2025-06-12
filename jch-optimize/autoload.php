<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2020 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

use JchOptimize\WordPress\Container\ContainerFactory;

if (! defined('_JCH_EXEC')) {
    define('_JCH_EXEC', 1);
}

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/vendor/scoper-autoload.php';

if (!class_exists('\JchOptimize\Container\ContainerFactory', false)) {
    class_alias(ContainerFactory::class, '\\JchOptimize\\Container\\ContainerFactory');
}