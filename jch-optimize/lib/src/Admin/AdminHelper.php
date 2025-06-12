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

namespace JchOptimize\Core\Admin;

use _JchOptimizeVendor\V91\GuzzleHttp\Client;
use _JchOptimizeVendor\V91\GuzzleHttp\Exception\GuzzleException;
use _JchOptimizeVendor\V91\GuzzleHttp\Psr7\Uri;
use _JchOptimizeVendor\V91\GuzzleHttp\Psr7\Utils as GuzzleUtils;
use _JchOptimizeVendor\V91\GuzzleHttp\RequestOptions;
use _JchOptimizeVendor\V91\Joomla\Filesystem\Exception\FilesystemException;
use _JchOptimizeVendor\V91\Joomla\Filesystem\File;
use _JchOptimizeVendor\V91\Joomla\Filesystem\Folder;
use _JchOptimizeVendor\V91\Composer\CaBundle\CaBundle;
use Exception;
use JchOptimize\Core\Helper;
use JchOptimize\Core\Helper as CoreHelper;
use JchOptimize\Core\Platform\PathsInterface;
use JchOptimize\Core\SystemUri;
use JchOptimize\Core\Uri\UriConverter;
use JchOptimize\Core\Uri\Utils;

use function array_search;
use function defined;
use function dirname;
use function file;
use function file_exists;
use function file_put_contents;
use function in_array;
use function is_dir;
use function ltrim;
use function pathinfo;
use function pow;
use function preg_quote;
use function preg_replace;
use function rawurldecode;
use function str_replace;
use function strtolower;
use function substr;

use const DIRECTORY_SEPARATOR;
use const PHP_EOL;

defined('_JCH_EXEC') or die('Restricted access');

class AdminHelper
{
    private ?array $optimizedFiles = null;

    public static string $optimizeImagesFileExtRegex = '\.(?:gif|jpe?g|png|webp)$';

    public function __construct(private PathsInterface $pathsUtils)
    {
    }

    /**
     * @deprecated
     */
    public function expandFileNameLegacy(string $sFile): string
    {
        $sSanitizedFile = str_replace('//', '/', $sFile);
        $aPathParts = pathinfo($sSanitizedFile);

        $sRelFile = str_replace([
            '_',
            '//'
        ], ['/', '_'], $aPathParts['basename']);

        return preg_replace(
            '#^' . preg_quote(ltrim(SystemUri::currentBasePath(), DIRECTORY_SEPARATOR)) . '#',
            $this->pathsUtils->rootPath() . DIRECTORY_SEPARATOR,
            $sRelFile
        );
    }

    public function expandFileName(string $file): string
    {
        $sanitizedFile = str_replace('//', '/', $file);
        $aPathParts = pathinfo($sanitizedFile);

        $expandedBasename = str_replace(['_', '//'], [DIRECTORY_SEPARATOR, '_'], $aPathParts['basename']);

        return $this->pathsUtils->rootPath() . DIRECTORY_SEPARATOR . ltrim($expandedBasename, DIRECTORY_SEPARATOR);
    }

    public function copyImage(string $src, string $dest): bool
    {
        try {
            $client = new Client([
                RequestOptions::VERIFY => CaBundle::getBundledCaBundlePath()
            ]);

            $uri = new Uri($src);
            if (str_starts_with($uri->getScheme(), 'http')) {
                $response = $client->get($uri);
                $srcStream = $response->getBody();
            } else {
                $srcStream = GuzzleUtils::streamFor(GuzzleUtils::tryFopen($src, 'rb'));
            }

            //Let's ensure parent directory for dest exists
            if (!file_exists(dirname($dest))) {
                Folder::create(dirname($dest));
            }

            GuzzleUtils::copyToStream(
                GuzzleUtils::streamFor($srcStream),
                GuzzleUtils::streamFor(GuzzleUtils::tryFopen($dest, 'wb'))
            );
        } catch (Exception|GuzzleException $e) {
            return false;
        }

        return true;
    }

    /**
     * @deprecated
     */
    public function contractFileNameLegacy(string $filePath): string
    {
        $difference = $this->subtractPath($filePath, CoreHelper::appendTrailingSlash($this->pathsUtils->rootPath()));

        return str_replace(
            ['_', '/'],
            ['__', '_'],
            $this->normalizePath($difference)
        );
    }

    public function contractFileName(string $filePath): string
    {
        $path = (string) UriConverter::filePathToUri($filePath, $this->pathsUtils)
            ->withScheme('')
            ->withHost('')
            ->withPort(null);

        return str_replace(
            ['_', '/'],
            ['__', '_'],
            $this->normalizePath(Helper::removeLeadingSlash($path))
        );
    }

    public function stringToBytes(string $value): int
    {
        $sUnit = strtolower(substr($value, -1, 1));

        return (int)((int)$value * pow(1024, array_search($sUnit, [1 => 'k', 'm', 'g'])));
    }

    public function markOptimized(string $file): void
    {
        $metafile = $this->getMetaFile();
        $metafileDir = dirname($metafile);

        try {
            if (
                !file_exists($metafileDir . '/index.html')
                || !file_exists($metafileDir . '/.htaccess')
            ) {
                $html = <<<HTML
<html><head><title></title></head><body></body></html>
HTML;
                File::write($metafileDir . '/index.html', $html);
                $htaccess = <<<APACHECONFIG
order deny,allow
deny from all

<IfModule mod_autoindex.c>
	Options -Indexes
</IfModule>
APACHECONFIG;
                File::write($metafileDir . '/.htaccess', $htaccess);
            }
        } catch (FilesystemException $e) {
        }

        if (is_dir($metafileDir)) {
            $file = $this->normalizePath($file);
            $file = $this->maskFileName($file) . PHP_EOL;
            if (!in_array($file, $this->getOptimizedFiles())) {
                File::write($metafile, $file, false, true);
            }
        }
    }

    public function maskFileName($file): string
    {
        return '[ROOT]/' . CoreHelper::removeLeadingSlash($this->subtractPath($file, $this->pathsUtils->rootPath()));
    }

    public function getMetaFile(): string
    {
        return $this->pathsUtils->rootPath() . DIRECTORY_SEPARATOR . '.jch' . DIRECTORY_SEPARATOR . 'jch-api2.txt';
    }

    public function getOptimizedFiles(): array
    {
        if ($this->optimizedFiles === null) {
            $this->optimizedFiles = $this->getCurrentOptimizedFiles();
        }

        return $this->optimizedFiles;
    }

    public function filterOptimizedFiles(array $images): array
    {
        $normalizedImages = array_map(function ($image) {
            return $this->normalizePath($image);
        }, $images);

        return array_diff($normalizedImages, $this->getOptimizedFiles());
    }

    public function isAlreadyOptimized(string $image): bool
    {
        return in_array($this->normalizePath($image), $this->getOptimizedFiles());
    }

    /**
     * @return string[]
     */
    protected function getCurrentOptimizedFiles(): array
    {
        $metafile = $this->getMetaFile();

        if (!file_exists($metafile)) {
            return [];
        }

        $optimizeds = file($metafile, FILE_IGNORE_NEW_LINES);

        if ($optimizeds === false) {
            $optimizeds = [];
        } else {
            $optimizeds = array_map(function (string $value) {
                return str_replace('[ROOT]', $this->pathsUtils->rootPath(), $value);
            }, $optimizeds);
        }

        return $optimizeds;
    }

    public function unmarkOptimized(string $file): void
    {
        $metafile = $this->getMetaFile();

        if (!@file_exists($metafile)) {
            return;
        }

        $aOptimizedFile = $this->getCurrentOptimizedFiles();

        if (($key = array_search($file, $aOptimizedFile)) !== false) {
            unset($aOptimizedFile[$key]);
        }

        $sContents = implode(PHP_EOL, $aOptimizedFile) . PHP_EOL;

        file_put_contents($metafile, $sContents);
    }

    public static function proOnlyField(): string
    {
        return <<<HTML
<fieldset style="padding: 5px 5px 0 0; color:darkred"><em>Only available in Pro Version!</em></fieldset>
HTML;
    }


    public function subtractPath(string $minuend, string $subtrahend): string
    {
        $minuendNormalized = $this->normalizePath($minuend);
        $subtrahendNormalized = $this->normalizePath($subtrahend);

        if (str_starts_with($minuendNormalized, $subtrahendNormalized)) {
            return substr($minuend, strlen($subtrahend));
        }

        return $minuend;
    }

    public function normalizePath(string $path): string
    {
        return rawurldecode((string)Utils::uriFor($path));
    }
}
