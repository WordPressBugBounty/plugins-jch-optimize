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

namespace JchOptimize\Core\Admin\Ajax;

use JchOptimize\Core\Admin\Json;
use JchOptimize\Core\Admin\MultiSelectItems;

\defined('_JCH_EXEC') or exit('Restricted access');
class MultiSelect extends \JchOptimize\Core\Admin\Ajax\Ajax
{
    public function run(): Json
    {
        $aData = $this->input->get('data', [], 'array');
        $container = $this->getContainer();

        /** @var MultiSelectItems $oAdmin */
        $oAdmin = $container->buildObject(MultiSelectItems::class);

        try {
            $oAdmin->getAdminLinks();
        } catch (\Exception $e) {
        }
        $response = [];
        foreach ($aData as $sData) {
            $options = $oAdmin->prepareFieldOptions($sData['type'], $sData['param'], $sData['group'], \false);
            $response[$sData['id']] = new Json($options);
        }

        return new Json($response);
    }
}
