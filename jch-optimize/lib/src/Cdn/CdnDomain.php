<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 *  @package   jchoptimize/core
 *  @author    Samuel Marshall <samuel@jch-optimize.net>
 *  @copyright Copyright (c) 2024 Samuel Marshall / JCH Optimize
 *  @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Cdn;

use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use JchOptimize\Core\Uri\Utils;

use function implode;

class CdnDomain
{
    private UriInterface $uri;

    private array $fileExtRegexArray;

    public function __construct(string|UriInterface $url, array $fileExtRegexArray, string $scheme)
    {
        $this->uri = $this->prepareDomain($url, $scheme);
        $this->fileExtRegexArray = $fileExtRegexArray;
    }

    private function prepareDomain(UriInterface|string $url, string $scheme): UriInterface
    {
        if (is_string($url)) {
            $url = '//' . preg_replace('#^(?:[^:/]++://)#', '', trim($url));
        }

        return Utils::uriFor($url)->withScheme($scheme);
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function getFileExtRegexArray(): array
    {
        return $this->fileExtRegexArray;
    }

    public function getFileExtRegexString(): string
    {
        return implode('|', $this->getFileExtRegexArray());
    }
}
