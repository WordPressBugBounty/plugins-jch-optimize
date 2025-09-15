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

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Joomla\Filesystem\Folder;
use _JchOptimizeVendor\V91\Psr\Http\Message\UriInterface;
use Exception;
use FilesystemIterator;
use JchOptimize\Core\Html\Elements\Script;
use JchOptimize\Core\Html\HtmlElementInterface;
use JchOptimize\Core\Uri\Utils;
use JchOptimize\Core\Platform\PathsInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_map;
use function defined;
use function file_exists;
use function html_entity_decode;
use function is_object;
use function preg_match;
use function preg_replace;
use function rmdir;
use function unlink;

defined('_JCH_EXEC') or die('Restricted access');

/**
 * Some helper functions
 *
 */
class Helper
{
    /**
     * Checks if file (can be external) exists
     *
     * @param   string  $sPath
     *
     * @return bool
     */
    public static function fileExists(string $sPath): bool
    {
        if ((str_starts_with($sPath, 'http'))) {
            $sFileHeaders = @get_headers($sPath);

            return ($sFileHeaders !== false && !str_contains($sFileHeaders[0], '404'));
        } else {
            return file_exists($sPath);
        }
    }

    public static function cleanReplacement(string $string): string
    {
        return strtr($string, ['\\' => '\\\\', '$' => '\$']);
    }


    /**
     * @return string
     * @deprecated
     */
    public static function getBaseFolder(): string
    {
        return SystemUri::currentBasePath();
    }

    public static function strReplace(string $search, string $replace, string $subject): string
    {
        return (string)str_replace(self::cleanPath($search), $replace, self::cleanPath($subject));
    }

    public static function cleanPath(string $str): string
    {
        return str_replace(['\\\\', '\\'], '/', $str);
    }

    /**
     * Determine if document is of XHTML doctype
     *
     * @param   string  $html
     *
     * @return bool
     */
    public static function isXhtml(string $html): bool
    {
        return (bool)preg_match('#^\s*+(?:<!DOCTYPE(?=[^>]+XHTML)|<\?xml.*?\?>)#i', trim($html));
    }

    /**
     * Determines if document is of html5 doctype
     *
     * @param   string  $html
     *
     * @return bool        True if doctype is html5
     */
    public static function isHtml5(string $html): bool
    {
        return (bool)preg_match('#^<!DOCTYPE html>#i', trim($html));
    }

    public static function getArray(mixed $value): array
    {
        if (is_array($value)) {
            $array = $value;
        } elseif (is_string($value)) {
            $array = explode(',', trim($value));
        } elseif (is_object($value)) {
            $array = (array)$value;
        } else {
            $array = [];
        }

        if (!empty($array)) {
            $array = array_map(function ($v) {
                if (is_string($v)) {
                    return trim($v);
                } elseif (is_object($v)) {
                    return (array)$v;
                } else {
                    return $v;
                }
            }, $array);
        }

        return array_filter($array);
    }

    public static function validateHtml(string $html): bool
    {
        return (bool)preg_match(
            '#^(?>(?><?[^<]*+)*?<html(?><?[^<]*+)*?<head(?><?[^<]*+)*?</head\s*+>)(?><?[^<]*+)*?'
            . '<body.*</body\s*+>(?><?[^<]*+)*?</html\s*+>#is',
            $html
        );
    }

    /**
     *
     * @param   string[]  $needles   Array of excluded values to compare against
     * @param   string    $haystack  The string we're testing to see if it was excluded
     * @param   string    $type      (css|js) No longer used
     *
     * @return bool
     */
    public static function findExcludes(array $needles, string $haystack, string $type = ''): bool
    {
        if (empty($needles)) {
            return false;
        }

        foreach ($needles as $needle) {
            //Remove all spaces from test string and excluded string
            $needle = preg_replace('#\s#', '', $needle);
            $haystack = preg_replace('#\s#', '', html_entity_decode($haystack));

            if ($needle && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param   string[]  $needles
     * @param   string    $haystack
     *
     * @return bool
     */
    public static function findMatches(array $needles, string $haystack): bool
    {
        return self::findExcludes($needles, $haystack);
    }

    public static function extractUrlsFromSrcset($srcSet): array
    {
        $strings = explode(',', $srcSet);
        $aUrls = array_map(function ($v) {
            $aUrlString = explode(' ', trim($v));

            return Utils::uriFor(array_shift($aUrlString));
        }, $strings);

        return $aUrls;
    }

    public static function isScriptDeferred(Script $script): bool
    {
        return $script->getType() == 'module'
            || ($script->getSrc() !== false && ($script->getDefer() || $script->getAsync()));
    }

    /**
     * Utility function to convert a rule set to a unique class. Will retain pseudo-classes or pseudo-elements
     *
     * @param   string  $selectorGroup
     *
     * @return string
     */
    public static function cssSelectorsToClass(string $selectorGroup): string
    {
        return '_jch-' . preg_replace(
            [
                '#\##',
                '#\.#',
                '#[^0-9a-z_:-]#i',
            ],
            [
                'id_',
                'cl_',
                '',
            ],
            $selectorGroup
        );
    }

    public static function deleteFolder(string $folder): bool
    {
        $it = new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator(
            $it,
            RecursiveIteratorIterator::CHILD_FIRST
        );
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($folder);

        return !file_exists($folder);
    }

    /**
     * Checks if a Uri is valid
     *
     * @param   UriInterface  $uri
     *
     * @return bool
     */
    public static function uriInvalid(UriInterface $uri): bool
    {
        if ((string)$uri == '') {
            return true;
        }

        if (
            $uri->getScheme() == ''
            && $uri->getAuthority() == ''
            && $uri->getQuery() == ''
            && $uri->getFragment() == ''
        ) {
            if ($uri->getPath() == '/' || $uri->getPath() == SystemUri::currentBasePath()) {
                return true;
            }
        }

        return false;
    }

    public static function isStaticFile(string $filePath): bool
    {
        return (bool) preg_match('#\.(?:css|js|png|jpe?g|gif|bmp|webp|svg)$#i', $filePath);
    }

    public static function createCacheFolder(Container $container): void
    {
        $cacheDir = $container->get(PathsInterface::class)->cacheDir();

        if (!file_exists($cacheDir)) {
            try {
                Folder::create($cacheDir);
            } catch (Exception $e) {
            }
        }
    }

    public static function appendTrailingSlash(string $path): string
    {
        return self::removeTrailingSlash($path) . '/';
    }

    public static function removeTrailingSlash(string $path): string
    {
        return rtrim($path, '/\\');
    }

    public static function appendLeadingSlash(string $path): string
    {
        return '/' . self::removeLeadingSlash($path);
    }

    public static function removeLeadingSlash(string $path): string
    {
        return ltrim($path, '/\\');
    }

    public static function getElementWidth(HtmlElementInterface $element): int
    {
        return (int)($element->attributeValue('width') ?: $element->attributeValue('data-width') ?: 0);
    }
}
