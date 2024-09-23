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

namespace JchOptimize\Core\Exception;

use ErrorException;

use function defined;

use const E_WARNING;

defined('_JCH_EXEC') or die('Restricted access');

class PregErrorException extends ErrorException implements ExceptionInterface
{
    public function __construct(
            $message = "",
            $code = 0,
            $severity = E_WARNING
    ) {
        parent::__construct($message, $code, $severity);
    }
}
