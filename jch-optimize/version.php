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

// Protect from unauthorized access
defined('_JCH_EXEC') or die;

const JCH_VERSION  = '4.2.1';
const JCH_DATE     = '2024-05-15';
const JCH_PRO      = '0';
const JCH_DEVELOP  = '0';
const JCH_PLATFORM = 'WordPress';
define('_JchOptimizeVendor\JPATH_ROOT', rtrim(ABSPATH, '/\\'));
