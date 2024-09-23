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

namespace JchOptimize\Core\Interfaces;

use _JchOptimizeVendor\Psr\Log\LoggerInterface;

\defined('_JCH_EXEC') or exit('Restricted access');
interface MvcLoggerInterface extends LoggerInterface
{
}
