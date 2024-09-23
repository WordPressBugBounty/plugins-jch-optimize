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

namespace JchOptimize\Core;

use JchOptimize\Platform\Utility;

\defined('_JCH_EXEC') or exit('Restricted access');
class Browser
{
    /**
     * @var Browser[]
     */
    protected static array $instances = [];

    /**
     * @var object{browser: string, browserVersion: string, os: string}
     */
    protected object $oClient;

    public function __construct(string $userAgent)
    {
        $this->oClient = Utility::userAgent($userAgent);
    }

    public static function getInstance(string $userAgent = ''): Browser
    {
        if ('' == $userAgent && isset($_SERVER['HTTP_USER_AGENT'])) {
            $userAgent = \trim($_SERVER['HTTP_USER_AGENT']);
        }
        $signature = \md5($userAgent);
        if (!isset(self::$instances[$signature])) {
            self::$instances[$signature] = new \JchOptimize\Core\Browser($userAgent);
        }

        return self::$instances[$signature];
    }

    public function getBrowser(): string
    {
        return $this->oClient->browser;
    }

    public function getVersion(): string
    {
        return $this->oClient->browserVersion;
    }
}
