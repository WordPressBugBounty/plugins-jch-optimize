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

namespace JchOptimize\Core;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriComparator;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\UriInterface;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

class FileUtils
{
    /**
     * Prepare a representation of a file URL or value for display, possibly truncated
     *
     * @param UriInterface|null $uri The string being prepared
     * @param string            $content
     * @param bool              $truncate If true will be truncated at specified length, prepending with an epsilon
     * @param int               $length The length in number of characters.
     *
     * @return string
     */
    public function prepareForDisplay(
        ?UriInterface $uri = null,
        string $content = '',
        bool $truncate = true,
        int $length = 27
    ): string {
        $eps = '';

        if ($uri) {
            /*   $uri = UriResolver::resolve(SystemUri::currentUri(), $uri);
               if ( ! UriComparator::isCrossOrigin(SystemUri::currentUri(), $uri)) {
                   $url = $uri->getPath();
               } else {
                   $url = Uri::composeComponents($uri->getScheme(), $uri->getAuthority(), $uri->getPath(), '', '');
               }*/
            $url = (string)$uri->withQuery('')->withFragment('');

            if (! $truncate) {
                return $url;
            }

            if (strlen($url) > $length) {
                $url = substr($url, -$length);
                $url = preg_replace('#^[^/]*+/#', '/', $url);
                $eps = '...';
            }

            return $eps . $url;
        }

        if (! $truncate) {
            return $content;
        }

        if (strlen($content) > 52) {
            $content = substr($content, 0, 52);
            $eps     = '...';
            $content = $content . $eps;
        }

        if (strlen($content) > 26) {
            $content = str_replace($content[26], $content[26] . "\n", $content);
        }

        return $eps . $content;
    }
}
