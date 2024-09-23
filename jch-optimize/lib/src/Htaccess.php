<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads.
 *
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core;

use _JchOptimizeVendor\Joomla\Filesystem\File;
use JchOptimize\Platform\Paths;

abstract class Htaccess
{
    /**
     * Will add contents to htaccess file within specified line delimiters
     * If target content already exists, will overwrite the section within delimiters.
     *
     * @throws Exception\FileNotFoundException
     */
    public static function updateHtaccess(string $content, array $lineDelimiters, string $position = 'prepend'): bool
    {
        if (\file_exists(self::getHtaccessFile())) {
            $delimitedContent = $lineDelimiters[0].\PHP_EOL.$content.\PHP_EOL.$lineDelimiters[1];
            // Get existing content of file, removing previous contents within delimiters if existing
            $cleanedContents = self::getCleanedHtaccessContents($lineDelimiters);

            switch ($position) {
                case 'append':
                    $content = $cleanedContents.\PHP_EOL.\PHP_EOL.$delimitedContent;

                    break;

                case 'prepend':
                    $content = $delimitedContent.\PHP_EOL.\PHP_EOL.$cleanedContents;

                    break;

                default:
                    // If neither 'append' not 'prepend' specified, $position should contain a marker in
                    // the htaccess file that if existing, the content will be appended to, otherwise,
                    // it is prepended to the file
                    $positionRegex = \preg_quote($position, '#').'\\s*?[r\\n]?';
                    if (\preg_match('#'.$positionRegex.'#', $cleanedContents)) {
                        $content = \preg_replace('#'.$positionRegex.'#', '\\0'.\PHP_EOL.\PHP_EOL.$delimitedContent.\PHP_EOL, $cleanedContents);
                    } else {
                        $content = $delimitedContent.\PHP_EOL.\PHP_EOL.$cleanedContents;
                    }
            }
            if ($content) {
                return File::write(self::getHtaccessFile(), $content);
            }
        }

        throw new \JchOptimize\Core\Exception\FileNotFoundException('Htaccess File doesn\'t exist');
    }

    /**
     * Will remove the target section from the htaccess file.
     */
    public static function cleanHtaccess(array $lineDelimiters): void
    {
        if (\file_exists(self::getHtaccessFile())) {
            $count = null;
            $cleanedContents = self::getCleanedHtaccessContents($lineDelimiters, $count);
            if ($cleanedContents && $count > 0) {
                File::write(self::getHtaccessFile(), $cleanedContents);
            }
        }
    }

    private static function getCleanedHtaccessContents(array $lineDelimiters, &$count = null): string
    {
        $contents = \file_get_contents(self::getHtaccessFile());
        $regex = '#[\\r\\n]*?\\s*?'.\preg_quote($lineDelimiters[0], '#').'.*?'.\preg_quote($lineDelimiters[1], '#').'\\s*[\\r\\n]*?#s';

        return \preg_replace($regex, \PHP_EOL.\PHP_EOL, $contents, -1, $count);
    }

    private static function getHtaccessFile(): string
    {
        return Paths::rootPath().'/.htaccess';
    }
}
