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

namespace JchOptimize\Core;

use JchOptimize\Core\Exception\PropertyNotFoundException;

\defined('_JCH_EXEC') or exit('Restricted access');
trait FileInfosUtilsTrait
{
    /**
     * @var null|FileUtils
     */
    private ?\JchOptimize\Core\FileUtils $fileUtils = null;

    /**
     * Truncate url at the '/' less than 40 characters prepending '...' to the string.
     */
    public function prepareFileUrl(array $fileInfos, string $type): string
    {
        $fileUtils = $this->getFileUtils();

        return isset($fileInfos['url']) ? $fileUtils->prepareForDisplay($fileInfos['url'], '', \true, 40) : ('css' == $type ? 'Style' : 'Script').' Declaration';
    }

    private function getFileUtils(): FileUtils
    {
        if ($this->fileUtils instanceof \JchOptimize\Core\FileUtils) {
            return $this->fileUtils;
        }

        throw new PropertyNotFoundException('FileUtils not set in '.\get_class($this));
    }
}
