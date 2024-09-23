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

namespace JchOptimize\Core;

use JchOptimize\Platform\Paths;
use Joomla\Filesystem\File;
use function file_exists;
use function file_get_contents;
use function preg_quote;
use function preg_replace;
use function rtrim;
use const PHP_EOL;

abstract class Htaccess
{
    public static function updateHtaccess(string $content, array $lineDelimiters, bool $append = true): void
    {
        $htaccessFile = self::getHtaccessFile();

        if (file_exists($htaccessFile)) {
            $delimitedContent = $lineDelimiters[0] . PHP_EOL . $content . PHP_EOL . $lineDelimiters[1];

            $cleanedContents = self::getCleanedHtaccessContents($lineDelimiters, $htaccessFile);

            if ($append) {
                $content = $cleanedContents . PHP_EOL . $delimitedContent;
                File::write($htaccessFile, $content);
            }
        }
    }

    public static function cleanHtaccess(array $lineDelimiters): void
    {
        $htaccessFile = self::getHtaccessFile();

        if (file_exists($htaccessFile)) {
            $cleanedContents = self::getCleanedHtaccessContents($lineDelimiters, $htaccessFile);

            File::write($htaccessFile, $cleanedContents);
        }
    }

    private static function getCleanedHtaccessContents(array $lineDelimiters, string $htaccessFile): string
    {
        $contents = file_get_contents($htaccessFile);
        $regex = '#[\r\n]*' . preg_quote($lineDelimiters[0]) . '.*?' . preg_quote(rtrim($lineDelimiters[1], "# \n\r\t\v\x00")) . '[^\r\n]*[r\n]*#s';

        return preg_replace($regex, PHP_EOL, $contents, -1, $count);
    }

    private static function getHtaccessFile(): string
    {
        return Paths::rootPath() . '/.htaccess';
    }
}
