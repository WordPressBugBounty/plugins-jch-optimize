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

use JchOptimize\Core\Exception\FileNotFoundException;
use JchOptimize\Core\Platform\PathsInterface;
use RuntimeException;
use Throwable;

use function file_exists;
use function is_resource;
use function preg_match;
use function preg_quote;
use function preg_replace;

use const PHP_EOL;

abstract class Htaccess
{
    /**
     * @throws Throwable
     */
    public static function updateHtaccess(
        PathsInterface $pathsUtils,
        string $directives,
        array $lineDelimiters,
        string $position = 'prepend'
    ): bool {
        return self::withLockedHtaccess(
            $pathsUtils,
            [self::class, 'updateHtaccessContent'],
            $lineDelimiters,
            $directives,
            $position
        );
    }

    private static function updateHtaccessContent(
        string $existingContents,
        array $delimiters,
        string $directives,
        string $position
    ): string {
        $delimitedContent = $delimiters[0] . PHP_EOL . $directives . PHP_EOL . $delimiters[1];

        //Get existing content of file, removing previous contents within delimiters if existing
        $cleanedContents = self::cleanHtaccessContents($existingContents, $delimiters);

        switch ($position) {
            case 'append':
                $updatedContents = $cleanedContents . PHP_EOL . PHP_EOL . $delimitedContent;
                break;
            case 'prepend':
                $updatedContents = $delimitedContent . PHP_EOL . PHP_EOL . $cleanedContents;
                break;
            default:
                //If neither 'append' not 'prepend' specified, $position should contain a marker in
                //the htaccess file that if existing, the content will be appended to, otherwise,
                //it is prepended to the file
                $positionRegex = preg_quote($position, "#") . '\s*?[\r\n]?';

                if (preg_match('#' . $positionRegex . '#', $cleanedContents)) {
                    $updatedContents = preg_replace(
                        '#' . $positionRegex . '#',
                        '\0' . PHP_EOL . PHP_EOL . $delimitedContent . PHP_EOL,
                        $cleanedContents
                    );
                } else {
                    $updatedContents = $delimitedContent . PHP_EOL . PHP_EOL . $cleanedContents;
                }
        }

        if ($updatedContents === null) {
            return $existingContents;
        }

        return $updatedContents;
    }

    /**
     * Will remove the target section from the htaccess file
     * @throws Throwable
     */
    public static function cleanHtaccess(PathsInterface $pathsUtils, array $lineDelimiters): void
    {
        self::withLockedHtaccess($pathsUtils, [self::class, 'cleanHtaccessContents'], $lineDelimiters);
    }

    private static function cleanHtaccessContents(string $existingContents, array $delimiters): string
    {
        $delimiter0 = preg_quote($delimiters[0], '#');
        $delimiter1 = preg_quote($delimiters[1], '#');
        $regex = "#[\r\n]*?\s*?{$delimiter0}.*?{$delimiter1}\s*[\r\n]*?#s";

        $cleaned = preg_replace($regex, PHP_EOL . PHP_EOL, $existingContents, -1, $count);
        $cleaned = preg_replace("/\R{3,}/", PHP_EOL . PHP_EOL, $cleaned);

        if ($cleaned === null) {
            return $existingContents;
        }

        return $cleaned;
    }

    /**
     * @throws Throwable
     */
    private static function withLockedHtaccess(
        PathsInterface $pathsUtils,
        callable $callback,
        array $delimiters,
        ?string $directives = null,
        ?string $position = null
    ): bool {
        $file = self::getHtaccessFile($pathsUtils);
        if (!file_exists($file)) {
            throw new Exception\FileNotFoundException('Htaccess file doesn\'t exist');
        }

        $fp = fopen($file, 'c+');
        if ($fp === false) {
            throw new Exception\FileNotFoundException('Htaccess File not writable');
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new RuntimeException('Could not lock .htaccess for writing');
            }

            // Read *after* lock
            rewind($fp);
            $contents = stream_get_contents($fp);
            if ($contents === false) {
                throw new RuntimeException('Could not read .htaccess for writing');
            }

            $newContents = $directives === null && $position === null
                ? $callback($contents, $delimiters)
                : $callback($contents, $delimiters, $directives, $position);

            if (!is_string($newContents)) {
                throw new RuntimeException('Htaccess update callback must return string');
            }

            if ($newContents === $contents) {
                flock($fp, LOCK_UN);
                fclose($fp);

                return true;
            }

            // Write in-place under lock
            rewind($fp);
            ftruncate($fp, 0);
            $bytes = fwrite($fp, $newContents);
            fflush($fp);

            flock($fp, LOCK_UN);
            fclose($fp);

            return $bytes !== false;
        } catch (Throwable $e) {
            // Best effort unlock/close
            if (is_resource($fp)) {
                @flock($fp, LOCK_UN);
                @fclose($fp);
            }
            throw $e;
        }
    }

    private static function getHtaccessFile(PathsInterface $pathsUtils): string
    {
        return $pathsUtils->rootPath() . '/.htaccess';
    }
}
