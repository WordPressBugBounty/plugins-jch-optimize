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

namespace JchOptimize\Core\Admin;

// No direct access
\defined('_JCH_EXEC') or exit('Restricted access');

class Json
{
    /**
     * Determines whether the request was successful.
     */
    public bool $success = \true;

    /**
     * The response message.
     */
    public string $message = '';

    /**
     * The error code.
     */
    public int $code = 0;

    /**
     * The response data.
     *
     * @var mixed
     */
    public $data = '';

    /**
     * Constructor.
     *
     * @param mixed  $response The Response data
     * @param string $message  The response message
     */
    public function __construct($response = null, $message = '')
    {
        $this->message = $message;
        // Check if we are dealing with an error
        if ($response instanceof \Exception) {
            // Prepare the error response
            $this->success = \false;
            $this->message = $response->getMessage();
            $this->code = $response->getCode();
        } else {
            $this->data = $response;
        }
    }

    /**
     * Magic toString method for sending the response in JSON format.
     *
     * @return string The response in JSON format
     */
    public function __toString()
    {
        @\header('Content-Type: application/json; charset=utf-8');
        if (\version_compare(\PHP_VERSION, '7.2', '>=')) {
            return \json_encode($this, \JSON_INVALID_UTF8_SUBSTITUTE);
        }

        return \json_encode($this);
    }
}
