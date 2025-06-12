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

use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use JchOptimize\Core\Uri\UriComparator;

use function defined;
use function htmlentities;
use function preg_replace;
use function strlen;
use function substr;

defined('_JCH_EXEC') or die('Restricted access');

class FileUtils
{
    /**
     * Prepare a representation of a file URL or value for display, possibly truncated
     *
     * @param UriInterface|null $uri The string being prepared
     * @param string $content
     * @param bool $truncate If true will be truncated at specified length, prepending with an epsilon
     * @param int $length The length in number of characters.
     *
     * @return string
     * @deprecated
     */
    public function prepareForDisplay(
        ?UriInterface $uri = null,
        string $content = '',
        bool $truncate = true,
        int $length = 60
    ): string {
        if ($uri) {
            return self::prepareFileForDisplay($uri, $truncate, $length);
        } else {
            return self::prepareContentForDisplay($content, $truncate, $length);
        }
    }

    public static function prepareFileForDisplay(UriInterface $uri, bool $truncate = true, int $length = 80): string
    {
        if (!$truncate) {
            return (string) $uri;
        }

        $eps = '';
        $preEps = '';
        $url = $uri->getPath();

        if (UriComparator::isCrossOrigin($uri)) {
            $host  = $uri->getHost();
            $length -= strlen($host) - 10;
            $preEps = $host;
        }

        if (strlen($url) > $length) {
            $url = substr($url, -$length);
            $url = preg_replace('#^[^/]*+/#', '/', $url);
            $preEps = $preEps !== '' ? $preEps . '/' : '';
            $eps = '...';
        }

        return $preEps . $eps . $url;
    }

    public static function prepareContentForDisplay(string $content, bool $truncate = true, int $length = 80): string
    {
        if (!$truncate) {
            return $content;
        }

        if (strlen($content) > $length) {
            $content = substr($content, 0, $length);
            $content = $content . '...';
        }

        return htmlentities($content);
    }

    public static function prepareContentValue(string $content, int $length = 60): string
    {
        return htmlentities(substr($content, 0, $length));
    }

    public static function prepareUrlValue(UriInterface $uri): string
    {
        if (!UriComparator::isCrossOrigin($uri)) {
               $uri = $uri->withScheme('')->withHost('')->withPort(null)->withUserInfo('');
        }

         return (string) $uri->withQuery('')->withFragment('');
    }

    public static function prepareOriginValue(UriInterface $uri): string
    {
        return (string) $uri->withPath('')->withQuery('')->withFragment('');
    }
}
