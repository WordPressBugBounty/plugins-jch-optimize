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

use _JchOptimizeVendor\GuzzleHttp\Psr7\Uri;
use _JchOptimizeVendor\GuzzleHttp\Psr7\UriComparator;
use _JchOptimizeVendor\GuzzleHttp\Psr7\UriResolver;
use _JchOptimizeVendor\Psr\Http\Message\UriInterface;

\defined('_JCH_EXEC') or exit('Restricted access');
class FileUtils
{
    /**
     * Prepare a representation of a file URL or value for display, possibly truncated.
     *
     * @param null|UriInterface $uri      The string being prepared
     * @param bool              $truncate If true will be truncated at specified length, prepending with an epsilon
     * @param int               $length   the length in number of characters
     */
    public function prepareForDisplay(?UriInterface $uri = null, string $content = '', bool $truncate = \true, int $length = 27): string
    {
        $eps = '';
        if ($uri) {
            /*   $uri = UriResolver::resolve(SystemUri::currentUri(), $uri);
                 if ( ! UriComparator::isCrossOrigin(SystemUri::currentUri(), $uri)) {
                     $url = $uri->getPath();
                 } else {
                     $url = Uri::composeComponents($uri->getScheme(), $uri->getAuthority(), $uri->getPath(), '', '');
                 }*/
            $url = (string) $uri->withQuery('')->withFragment('');
            if (!$truncate) {
                return $url;
            }
            if (\strlen($url) > $length) {
                $url = \substr($url, -$length);
                $url = \preg_replace('#^[^/]*+/#', '/', $url);
                $eps = '...';
            }

            return $eps.$url;
        }
        if (!$truncate) {
            return $content;
        }
        if (\strlen($content) > 52) {
            $content = \substr($content, 0, 52);
            $eps = '...';
            $content = $content.$eps;
        }
        if (\strlen($content) > 26) {
            $content = \str_replace($content[26], $content[26]."\n", $content);
        }

        return $eps.$content;
    }
}
