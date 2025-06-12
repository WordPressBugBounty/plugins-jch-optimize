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

use _JchOptimizeVendor\V91\GuzzleHttp\Psr7\UriResolver;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use JchOptimize\Core\Cdn\Cdn;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\SystemUri;

use function str_replace;

final class UriConverter
{
    public static function uriToFilePath(UriInterface $uri, PathsInterface $pathsUtils, Cdn $cdn): string
    {
        $resolvedUri = UriResolver::resolve(SystemUri::currentUri(), $uri);

        $path = str_replace(
            array_map(fn ($domain) => (string)$domain, Utils::originDomains($pathsUtils, $cdn)),
            Helper::appendTrailingSlash($pathsUtils->rootPath()),
            (string)$resolvedUri->withQuery('')->withFragment('')
        );

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

    public static function filePathToUri(
        string|UriInterface $url,
        PathsInterface $pathsUtils,
        ?Cdn $cdn = null
    ): UriInterface {
        $uri = Utils::uriFor($url);
        $rootUri = Utils::uriFor(Helper::appendTrailingSlash($pathsUtils->rootPath()));
        $relPath = str_replace((string)$rootUri, '', (string)$uri);

        $uri = UriResolver::resolve(Utils::uriFor(SystemUri::siteBaseFull($pathsUtils)), Utils::uriFor($relPath));

        if ($cdn) {
            return $cdn->loadCdnResource($uri);
        }

        return $uri;
    }
}
