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

use _JchOptimizeVendor\Composer\CaBundle\CaBundle;
use _JchOptimizeVendor\GuzzleHttp\Client;
use _JchOptimizeVendor\GuzzleHttp\Psr7\Utils as GuzzleUtils;
use _JchOptimizeVendor\GuzzleHttp\RequestOptions;
use _JchOptimizeVendor\Joomla\Filesystem\Exception\FilesystemException;
use _JchOptimizeVendor\Joomla\Filesystem\File;
use _JchOptimizeVendor\Joomla\Filesystem\Folder;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\Utils;
use JchOptimize\Platform\Paths;

use function file;

\defined('_JCH_EXEC') or exit('Restricted access');
class Helper
{
    /**
     * @return null|array|string|string[]
     *
     * @deprecated
     */
    public static function expandFileNameLegacy(string $sFile)
    {
        $sSanitizedFile = \str_replace('//', '/', $sFile);
        $aPathParts = \pathinfo($sSanitizedFile);
        $sRelFile = \str_replace(['_', '//'], ['/', '_'], $aPathParts['basename']);

        return \preg_replace('#^'.\preg_quote(\ltrim(SystemUri::basePath(), \DIRECTORY_SEPARATOR)).'#', Paths::rootPath().\DIRECTORY_SEPARATOR, $sRelFile);
    }

    public static function expandFileName(string $file): string
    {
        $sanitizedFile = \str_replace('//', '/', $file);
        $aPathParts = \pathinfo($sanitizedFile);
        $expandedBasename = \str_replace(['_', '//'], [\DIRECTORY_SEPARATOR, '_'], $aPathParts['basename']);

        return Paths::rootPath().\DIRECTORY_SEPARATOR.\ltrim($expandedBasename, \DIRECTORY_SEPARATOR);
    }

    public static function copyImage(string $src, string $dest): bool
    {
        try {
            $client = new Client([RequestOptions::VERIFY => CaBundle::getBundledCaBundlePath()]);
            $uri = Utils::uriFor($src);
            if (0 === \strpos($uri->getScheme(), 'http')) {
                $response = $client->get($uri);
                $srcStream = $response->getBody();
            } else {
                $srcStream = GuzzleUtils::streamFor(GuzzleUtils::tryFopen($src, 'rb'));
            }
            // Let's ensure parent directory for dest exists
            if (!\file_exists(\dirname($dest))) {
                Folder::create(\dirname($dest));
            }
            GuzzleUtils::copyToStream(GuzzleUtils::streamFor($srcStream), GuzzleUtils::streamFor(GuzzleUtils::tryFopen($dest, 'wb')));
        } catch (\Exception $e) {
            return \false;
        }

        return \true;
    }

    /**
     * @deprecated
     */
    public static function contractFileNameLegacy(string $fileName): string
    {
        return \str_replace([Paths::rootPath().\DIRECTORY_SEPARATOR, '_', \DIRECTORY_SEPARATOR], [\ltrim(SystemUri::basePath(), \DIRECTORY_SEPARATOR), '__', '_'], $fileName);
    }

    /**
     * Returns the 'contracted' path of the file relative to the Uri base as opposed to the web root as in legacy.
     */
    public static function contractFileName(string $filePath): string
    {
        $difference = self::subtractPath($filePath, Paths::rootPath().'/');

        return \str_replace(['_', '\\', '/'], ['__', '_', '_'], $difference);
    }

    /**
     * @return array{path:string}
     */
    public static function prepareImageUrl(string $image): array
    {
        // return array('path' => Utility::encrypt($image));
        return ['path' => $image];
    }

    /**
     * @param false|string $value
     *
     * @return float|int
     */
    public static function stringToBytes($value)
    {
        $sUnit = \strtolower(\substr($value, -1, 1));

        return (int) $value * \pow(1024, \array_search($sUnit, [1 => 'k', 'm', 'g']));
    }

    public static function markOptimized(string $file): void
    {
        $metafile = self::getMetaFile();
        $metafileDir = \dirname($metafile);

        try {
            if (!\file_exists($metafileDir.'/index.html') || !\file_exists($metafileDir.'/.htaccess')) {
                $html = <<<'HTML'
<html><head><title></title></head><body></body></html>
HTML;
                File::write($metafileDir.'/index.html', $html);
                $htaccess = <<<APACHECONFIG
order deny,allow
deny from all

<IfModule mod_autoindex.c>
\tOptions -Indexes
</IfModule>
APACHECONFIG;
                File::write($metafileDir.'/.htaccess', $htaccess);
            }
        } catch (FilesystemException $e) {
        }
        if (\is_dir($metafileDir)) {
            $file = self::normalizePath($file);
            $file = self::maskFileName($file).\PHP_EOL;
            if (!\in_array($file, self::getOptimizedFiles())) {
                File::write($metafile, $file, \false, \true);
            }
        }
    }

    public static function maskFileName($file): string
    {
        return '[ROOT]/'.\ltrim(self::subtractPath($file, Paths::rootPath()), '/');
    }

    public static function getMetaFile(): string
    {
        return Paths::rootPath().\DIRECTORY_SEPARATOR.'.jch'.\DIRECTORY_SEPARATOR.'jch-api2.txt';
    }

    public static function getOptimizedFiles(): array
    {
        static $optimizeds = null;
        if (\is_null($optimizeds)) {
            $optimizeds = self::getCurrentOptimizedFiles();
        }

        return $optimizeds;
    }

    public static function filterOptimizedFiles(array $images): array
    {
        $normalizedImages = \array_map(function ($image) {
            return self::normalizePath($image);
        }, $images);

        return \array_diff($normalizedImages, self::getOptimizedFiles());
    }

    public static function isAlreadyOptimized(string $image): bool
    {
        return \in_array(self::normalizePath($image), self::getOptimizedFiles());
    }

    /**
     * @param null|(mixed|string)[]|string $file
     *
     * @psalm-param array<mixed|string>|null|string $file
     */
    public static function unmarkOptimized($file)
    {
        $metafile = self::getMetaFile();
        if (!@\file_exists($metafile)) {
            return;
        }
        $aOptimizedFile = self::getCurrentOptimizedFiles();
        if (($key = \array_search($file, $aOptimizedFile)) !== \false) {
            unset($aOptimizedFile[$key]);
        }
        $sContents = \implode(\PHP_EOL, $aOptimizedFile).\PHP_EOL;
        \file_put_contents($metafile, $sContents);
    }

    public static function proOnlyField(): string
    {
        return '<fieldset style="padding: 5px 5px 0 0; color:darkred"><em>Only available in Pro Version!</em></fieldset>';
    }

    public static function subtractPath(string $minuend, string $subtrahend): string
    {
        $minuendNormalized = self::normalizePath($minuend);
        $subtrahendNormalized = self::normalizePath($subtrahend);
        if (0 === \strpos($minuendNormalized, $subtrahendNormalized)) {
            return \substr($minuend, \strlen($subtrahend));
        }

        return $minuend;
    }

    public static function normalizePath(string $path): string
    {
        return \rawurldecode((string) Utils::uriFor($path));
    }

    /**
     * @return string[]
     *
     * @psalm-return list<string>
     */
    protected static function getCurrentOptimizedFiles(): array
    {
        $metafile = self::getMetaFile();
        if (!\file_exists($metafile)) {
            return [];
        }
        $optimizeds = \file($metafile, \FILE_IGNORE_NEW_LINES);
        if (\false === $optimizeds) {
            $optimizeds = [];
        } else {
            $optimizeds = \array_map(function (string $value) {
                return \str_replace('[ROOT]', Paths::rootPath(), $value);
            }, $optimizeds);
        }

        return $optimizeds;
    }
}
