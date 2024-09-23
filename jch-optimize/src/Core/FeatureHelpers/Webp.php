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

namespace JchOptimize\Core\FeatureHelpers;

use JchOptimize\Core\Admin\Helper as AdminHelper;
use JchOptimize\Core\Browser;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Uri\UriComparator;
use JchOptimize\Core\Uri\UriConverter;
use JchOptimize\Core\Uri\UriNormalizer;
use JchOptimize\Core\Uri\Utils;
use JchOptimize\Platform\Paths;
use Joomla\Filesystem\Folder;
use Psr\Http\Message\UriInterface;

use function array_map;
use function defined;
use function file_exists;
use function in_array;
use function pathinfo;
use function rawurldecode;
use function str_replace;

defined('_JCH_EXEC') or die('Restricted access');

class Webp extends AbstractFeatureHelper
{
    private bool $testRunning = false;

    /**
     * @param array{7:string, 9:string, fullMatch:string, elementName:string, srcAttribute:string|false, srcValue:UriInterface|false, srcsetAttribute:string|false, srcsetValue:string|false, cssUrl:string|false, cssUrlValue:UriInterface|false}  $matches
     *
     * @return array{7:string, 9:string, fullMatch:string, elementName:string, srcAttribute:string|false, srcValue:UriInterface|false, srcsetAttribute:string|false, srcsetValue:string|false, cssUrl:string|false, cssUrlValue:UriInterface|false}
     */
    public function convert(array $matches): array
    {
        if (!in_array($matches['elementName'], [
                'img',
                'input',
                'picture',
                'iframe',
                'source',
                'video',
                'audio'
            ]) && $matches['cssUrlValue']
        ) {
            $cssWebpUrl = $this->getWebpImages($matches['cssUrlValue']);
            if (!is_null($cssWebpUrl)) {
                $matches['fullMatch'] = str_replace($matches[9], (string)$cssWebpUrl, $matches['fullMatch']);
                $matches['cssUrl'] = str_replace($matches[9], (string)$cssWebpUrl, $matches['cssUrl']);
                $matches['styleAttribute'] = str_replace($matches[9], (string)$cssWebpUrl, $matches['styleAttribute']);
                $matches['cssUrlValue'] = $cssWebpUrl;
            }
        } elseif (in_array($matches['elementName'], ['img', 'input'])
            && $matches['srcAttribute'] !== false && $matches['srcValue'] !== false) {
            $srcWebpValue = $this->getWebpImages($matches['srcValue']);
            if (!is_null($srcWebpValue)) {
                $matches['fullMatch'] = str_replace((string)$matches[7], (string)$srcWebpValue, $matches['fullMatch']);
                $matches['srcAttribute'] = str_replace((string)$matches[7], (string)$srcWebpValue, $matches['srcAttribute']);
                $matches['srcValue'] = $srcWebpValue;
            }

            if ($matches['srcsetValue'] !== false) {
                $urls = Helper::extractUrlsFromSrcset($matches['srcsetValue']);
                $webpUrls = array_map(function (?string $v) {
                    return (string) ($this->getWebpImages(Utils::uriFor($v)) ?? $v);
                }, $urls);

                if ($urls != $webpUrls) {
                    $matches['fullMatch'] = str_replace(
                        $urls,
                        $webpUrls,
                        $matches['fullMatch']
                    );
                    $matches['srcsetAttribute'] = str_replace(
                        $urls,
                        $webpUrls,
                        $matches['srcsetAttribute']
                    );
                    $matches['srcsetValue'] = str_replace(
                        $urls,
                        $webpUrls,
                        $matches['srcsetValue']
                    );
                }
            }
        }

        return $matches;
    }

    public function getWebpImages(UriInterface $imageUri): ?UriInterface
    {
        if ($imageUri->getScheme() == 'data' || !self::canIUse()) {
            return $imageUri;
        }

        $imagePath = UriConverter::uriToFilePath($imageUri);

        $aPotentialPaths = [
            self::getWebpPath($imagePath),
            self::getWebpPathLegacy($imagePath),
        ];

        foreach ($aPotentialPaths as $potentialWebpPath) {
            if ($this->fileExists($potentialWebpPath)) {
                //replace file system path with root relative path
                $webpRootUrl = str_replace(
                    Paths::nextGenImagesPath(),
                    Paths::nextGenImagesPath(true),
                    $potentialWebpPath
                );

                $webpImageUri = $imageUri->withPath($webpRootUrl);

                if (!UriComparator::isCrossOrigin($webpImageUri)) {
                    return UriConverter::absToNetworkPathReference($webpImageUri);
                }

                return $webpImageUri;
            }
        }

        return null;
    }

    public function fileExists(string $path): bool
    {
        if ($this->testRunning) {
            return true;
        }

        return @file_exists($path);
    }

    /**
     * Tries to determine if client supports WEBP images based on https://caniuse.com/webp
     */
    protected static function canIUse(): bool
    {
        $browser = Browser::getInstance();
        $browserName = $browser->getBrowser();

        //WEBP only supported in Safari running on MacOS 11 or higher, best to avoid.
        if ($browserName == 'Internet Explorer' || $browserName == 'Safari') {
            return false;
        }

        return true;
    }

    /**
     * @param string $originalImagePath
     * @return string
     */
    public static function getWebpPathLegacy(string $originalImagePath): string
    {
        if (!file_exists(Paths::nextGenImagesPath())) {
            Folder::create(Paths::nextGenImagesPath());
        }

        $fileParts = pathinfo(AdminHelper::contractFileNameLegacy($originalImagePath));

        return Paths::nextGenImagesPath() . '/' . $fileParts['filename'] . '.webp';
    }

    /**
     * @param string $originalImagePath
     * @return string
     */
    public static function getWebpPath(string $originalImagePath): string
    {
        if (!file_exists(Paths::nextGenImagesPath())) {
            Folder::create(Paths::nextGenImagesPath());
        }

        $fileParts = pathinfo(AdminHelper::contractFileName($originalImagePath));

        return Paths::nextGenImagesPath() . '/' . rawurldecode($fileParts['filename']) . '.webp';
    }

    public function enableTestRunning(): void
    {
        $this->testRunning = true;
    }
}
