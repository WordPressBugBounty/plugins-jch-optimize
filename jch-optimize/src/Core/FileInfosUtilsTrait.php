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

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

trait FileInfosUtilsTrait
{
    /**
     * @var FileUtils
     */
    private FileUtils $fileUtils;

    /**
     * Truncate url at the '/' less than 40 characters prepending '...' to the string
     *
     * @param   array   $fileInfos
     * @param   string  $type
     *
     * @return string
     */
    public function prepareFileUrl(array $fileInfos, string $type): string
    {
        $sUrl = isset($fileInfos['url']) ?
                $this->fileUtils->prepareForDisplay($fileInfos['url'], '', true, 40) :
                ($type == 'css' ? 'Style' : 'Script') . ' Declaration';

        return $sUrl;
    }

}
