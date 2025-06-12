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

use _JchOptimizeVendor\V91\Joomla\Filesystem\File;
use JchOptimize\Core\Platform\PathsInterface;

use function file_exists;
use function file_get_contents;
use function preg_quote;
use function preg_replace;

use const PHP_EOL;

abstract class Htaccess
{
    public static function updateHtaccess(
        PathsInterface $pathsUtils,
        string $content,
        array $lineDelimiters,
        string $position = 'prepend'
    ): bool {
        if (file_exists(self::getHtaccessFile($pathsUtils))) {
            $delimitedContent = $lineDelimiters[0] . PHP_EOL . $content . PHP_EOL . $lineDelimiters[1];

            //Get existing content of file, removing previous contents within delimiters if existing
            $cleanedContents = self::getCleanedHtaccessContents($pathsUtils, $lineDelimiters);

            switch ($position) {
                case 'append':
                    $content = $cleanedContents . PHP_EOL . PHP_EOL . $delimitedContent;
                    break;
                case 'prepend':
                    $content = $delimitedContent . PHP_EOL . PHP_EOL . $cleanedContents;
                    break;
                default:
                    //If neither 'append' not 'prepend' specified, $position should contain a marker in
                    //the htaccess file that if existing, the content will be appended to, otherwise,
                    //it is prepended to the file
                    $positionRegex = preg_quote($position, "#") . '\s*?[r\n]?';

                    if (preg_match('#' . $positionRegex . '#', $cleanedContents)) {
                        $content = preg_replace(
                            '#' . $positionRegex . '#',
                            '\0' . PHP_EOL . PHP_EOL . $delimitedContent . PHP_EOL,
                            $cleanedContents
                        );
                    } else {
                        $content = $delimitedContent . PHP_EOL . PHP_EOL . $cleanedContents;
                    }
            }

            if ($content) {
                return File::write(self::getHtaccessFile($pathsUtils), $content);
            }
        }

        throw new Exception\FileNotFoundException('Htaccess File doesn\'t exist');
    }

    /**
     * Will remove the target section from the htaccess file
     */
    public static function cleanHtaccess(PathsInterface $pathsUtils, array $lineDelimiters): void
    {
        if (file_exists(self::getHtaccessFile($pathsUtils))) {
            $count = null;
            $cleanedContents = self::getCleanedHtaccessContents($pathsUtils, $lineDelimiters, $count);

            if ($cleanedContents && $count > 0) {
                File::write(self::getHtaccessFile($pathsUtils), $cleanedContents);
            }
        }
    }

    private static function getCleanedHtaccessContents(
        PathsInterface $pathsUtils,
        array $lineDelimiters,
        &$count = null
    ): string {
        $contents = file_get_contents(self::getHtaccessFile($pathsUtils));

        $regex = '#[\r\n]*?\s*?' . preg_quote($lineDelimiters[0], '#') . '.*?' . preg_quote(
            $lineDelimiters[1],
            '#'
        ) . '\s*[\r\n]*?#s';

        return preg_replace($regex, PHP_EOL . PHP_EOL, $contents, -1, $count);
    }

    private static function getHtaccessFile(PathsInterface $pathsUtils): string
    {
        return $pathsUtils->rootPath() . '/.htaccess';
    }
}
