<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Uri;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use JchOptimize\Core\SystemUri;
use JchOptimize\Platform\Paths;
use Psr\Http\Message\UriInterface;

final class UriConverter
{
    public static function uriToFilePath(UriInterface $uri): string
    {
        $resolvedUri = UriResolver::resolve(SystemUri::currentUri(), $uri);

        $path = str_replace(Utils::originDomains(), Paths::rootPath() . '/', (string)$resolvedUri->withQuery('')->withFragment(''));

        //convert all directory to unix style
        return strtr(rawurldecode($path), '\\', '/');
    }

    public static function absToNetworkPathReference(UriInterface $uri): UriInterface
    {
        if (!Uri::isAbsolute($uri)) {
            return $uri;
        }

        if ($uri->getUserInfo() != '') {
            return $uri;
        }

        return $uri->withScheme('')->withHost('')->withPort(null);
    }
}
