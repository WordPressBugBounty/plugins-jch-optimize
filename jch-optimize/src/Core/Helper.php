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

use FilesystemIterator;
use JchOptimize\Platform\Paths;
use JchOptimize\Platform\Plugin;
use Joomla\Filesystem\Folder;
use Joomla\Registry\Registry;
use Psr\Http\Message\UriInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_map;
use function defined;
use function file_exists;
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
     * @param string $sPath
     *
     * @return bool
     */
    public static function fileExists(string $sPath): bool
    {
        if ((strpos($sPath, 'http') === 0)) {
            $sFileHeaders = @get_headers($sPath);

            return ($sFileHeaders !== false && strpos($sFileHeaders[0], '404') === false);
        } else {
            return file_exists($sPath);
        }
    }

    /**
     *
     * @return bool
     */
    public static function isMsieLT10(): bool
    {
        //$browser = Browser::getInstance( 'Mozilla/5.0 (Macintosh; Intel Mac OS X10_15_7) AppleWebkit/605.1.15 (KHTML, like Gecko) Version/14.1 Safari/605.1.15' );
        /** @var Browser $browser */
        $browser = Browser::getInstance();

        return ($browser->getBrowser() == 'Internet Explorer' && version_compare($browser->getVersion(), '10', '<'));
    }

    /**
     *
     * @param string $string
     *
     * @return string
     */
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
        return SystemUri::basePath();
    }

    /**
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     *
     * @return string
     */
    public static function strReplace(string $search, string $replace, string $subject): string
    {
        return str_replace(self::cleanPath($search), $replace, self::cleanPath($subject));
    }

    /**
     *
     * @param string $str
     *
     * @return string|string[]
     */
    public static function cleanPath(string $str)
    {
        return str_replace(['\\\\', '\\'], '/', $str);
    }

    /**
     * Determine if document is of XHTML doctype
     *
     * @param string $html
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
     * @param string $html
     *
     * @return bool        True if doctype is html5
     */
    public static function isHtml5(string $html): bool
    {
        return (bool)preg_match('#^<!DOCTYPE html>#i', trim($html));
    }

    /**
     * Splits a string into an array using any regular delimiter or whitespace
     *
     * @param string|array $string Delimited string of components
     *
     * @return string[]            An array of the components
     */
    public static function getArray($string): array
    {
        if (is_array($string)) {
            $array = $string;
        } elseif (is_string($string)) {
            $array = explode(',', trim($string));
        } else {
            $array = [];
        }

        if (!empty($array)) {
            $array = array_map(function ($value) {
                if (is_string($value)) {
                    return trim($value);
                } elseif (is_object($value)) {
                    return (array)$value;
                } else {
                    return $value;
                }
            }, $array);
        }

        return array_filter($array);
    }

    /**
     * @param string $url
     * @param Registry $params
     * @param array $posts
     * @param $logger
     *
     * @deprecated 
     *            //Being used in Sprite Controller
     */
    public static function postAsync(string $url, Registry $params, array $posts, $logger): void
    {
        $post_params = [];

        foreach ($posts as $key => &$val) {
            if (is_array($val)) {
                $val = implode(',', $val);
            }

            $post_params[] = $key . '=' . urlencode($val);
        }

        $post_string = implode('&', $post_params);

        $parts = Helper::parseUrl($url);

        if (isset($parts['scheme']) && ($parts['scheme'] == 'https')) {
            $protocol = 'ssl://';
            $default_port = 443;
        } else {
            $protocol = '';
            $default_port = 80;
        }

        $fp = @fsockopen(
            $protocol . $parts['host'],
            $parts['port'] ?? $default_port,
            $errno,
            $errstr,
            1
        );

        if (!$fp) {
            $logger->error($errno . ': ' . $errstr, $params);
            $logger->debug($errno . ': ' . $errstr, 'JCH_post-error');
        } else {
            $out = "POST " . $parts['path'] . '?' . $parts['query'] . " HTTP/1.1\r\n";
            $out .= "Host: " . $parts['host'] . "\r\n";
            $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $out .= "Content-Length: " . strlen($post_string) . "\r\n";
            $out .= "Connection: Close\r\n\r\n";

            if (isset($post_string)) {
                $out .= $post_string;
            }

            fwrite($fp, $out);
            fclose($fp);
            $logger->debug($out, 'JCH_post');
        }
    }

    /**
     *
     * @param string $sUrl
     *
     * @return array
     */
    public static function parseUrl(string $sUrl): array
    {
        preg_match(
            '#^(?:([a-z][a-z0-9+.-]*+):)?(?://(?:([^:@/]*+)(?::([^@/]*+))?@)?([^:/]++)(?::([^/]*+))?)?([^?\#\n]*+)?(?:\?([^\#\n]*+))?(?:\#(.*+))?$#i',
            $sUrl,
            $m
        );

        $parts = [];

        $parts['scheme'] = !empty($m[1]) ? $m[1] : null;
        $parts['user'] = !empty($m[2]) ? $m[2] : null;
        $parts['pass'] = !empty($m[3]) ? $m[3] : null;
        $parts['host'] = !empty($m[4]) ? $m[4] : null;
        $parts['port'] = !empty($m[5]) ? $m[5] : null;
        $parts['path'] = !empty($m[6]) ? $m[6] : '';
        $parts['query'] = !empty($m[7]) ? $m[7] : null;
        $parts['fragment'] = !empty($m[8]) ? $m[8] : null;

        return $parts;
    }

    /**
     *
     * @param string $html
     *
     * @return false|int
     */
    public static function validateHtml(string $html)
    {
        return preg_match(
            '#^(?>(?><?[^<]*+)*?<html(?><?[^<]*+)*?<head(?><?[^<]*+)*?</head\s*+>)(?><?[^<]*+)*?'
            . '<body.*</body\s*+>(?><?[^<]*+)*?</html\s*+>#is',
            $html
        );
    }

    /**
     *
     * @param array $excludedStringsArray Array of excluded values to compare against
     * @param string $testString The string we're testing to see if it was excluded
     * @param string $type (css|js) No longer used
     *
     * @return bool
     */
    public static function findExcludes(array $excludedStringsArray, string $testString, string $type = ''): bool
    {
        if (empty($excludedStringsArray)) {
            return false;
        }

        foreach ($excludedStringsArray as $excludedString) {
            //Remove all spaces from test string and excluded string
            $excludedString = preg_replace('#\s#', '', $excludedString);
            $testString = preg_replace('#\s#', '', $testString);

            if ($excludedString && strpos(htmlspecialchars_decode($testString), $excludedString) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function extractUrlsFromSrcset($srcSet): array
    {
        $strings = explode(',', $srcSet);
        $aUrls = array_map(function ($v) {
            $aUrlString = explode(' ', trim($v));

            return array_shift($aUrlString);
        }, $strings);

        return $aUrls;
    }

    /**
     * Utility function to convert a rule set to a unique class
     *
     * @param $selectorGroup
     *
     * @return string
     */
    public static function cssSelectorsToClass(string $selectorGroup): string
    {
        return '_jch-' . preg_replace('#[^0-9a-z_-]#i', '', $selectorGroup);
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
     * @param UriInterface $uri
     *
     * @return bool
     */
    public static function uriInvalid(UriInterface $uri): bool
    {
        if ((string)$uri == '') {
            return true;
        }

        if ($uri->getScheme() == ''
            && $uri->getAuthority() == ''
            && $uri->getQuery() == ''
            && $uri->getFragment() == '') {
            if ($uri->getPath() == '/' || $uri->getPath() == SystemUri::basePath()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return false|int
     *
     * @psalm-return 0|1|false
     */
    public static function isStaticFile(string $filePath)
    {
        return preg_match('#\.(?:css|js|png|jpe?g|gif|bmp|webp|svg)$#i', $filePath);
    }

    public static function createCacheFolder(): void
    {
        if (!file_exists(Paths::cacheDir())) {
            try {
                Folder::create(Paths::cacheDir());
            } catch (\Exception $exception) {
            }
        }
    }
}
