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

if (! defined('_JCH_EXEC')) {
    define('_JCH_EXEC', 1);
}

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/vendor/autoload.php';

if (JCH_DEVELOP) {
    require_once __DIR__ . '/lib-dev/vendor/autoload.php';
    require_once __DIR__ . '/lib-dev/src/class_map.php';
} else {
    require_once __DIR__ . '/lib/vendor/scoper-autoload.php';
    require_once __DIR__ . '/lib/src/class_map.php';
}
