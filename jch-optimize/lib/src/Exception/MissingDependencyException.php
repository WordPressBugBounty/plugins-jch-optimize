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

namespace JchOptimize\Core\Exception;

\defined('_JCH_EXEC') or exit('Restricted access');
class MissingDependencyException extends \JchOptimize\Core\Exception\RuntimeException implements \JchOptimize\Core\Exception\ExceptionInterface
{
}
