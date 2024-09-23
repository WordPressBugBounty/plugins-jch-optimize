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

use JchOptimize\Core\SystemUri;

\defined('_JCH_EXEC') or exit('Restricted access');
class PregErrorException extends \ErrorException implements \JchOptimize\Core\Exception\ExceptionInterface
{
    public function __construct($message = '', $code = 0, $severity = \E_WARNING)
    {
        $message .= ': '.SystemUri::currentUrl();
        parent::__construct($message, $code, $severity);
    }
}
