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

namespace JchOptimize\Core\Admin\Ajax;

use Exception;
use JchOptimize\Core\Admin\AbstractHtml;
use JchOptimize\Core\Admin\Json;
use JchOptimize\Core\Admin\MultiSelectItems;

use function array_column;
use function array_filter;
use function array_intersect;
use function array_map;
use function count;
use function defined;
use function preg_match;
use function preg_replace;

defined('_JCH_EXEC') or die('Restricted access');

class SmartCombine extends Ajax
{
    public function run(): Json
    {
        $container = $this->getContainer();
        /** @var MultiSelectItems $oAdmin */
        $oAdmin = $container->get(MultiSelectItems::class);
        /** @var AbstractHtml $oHtml */
        $oHtml = $container->get(AbstractHtml::class);

        try {
            $aHtml = $oHtml->getCrawledHtmls(['crawl_limit' => 3]);
            $aLinksArray = [];

            foreach ($aHtml[0] as $sHtml) {
                $aLinks = $oAdmin->generateAdminLinks($sHtml['html'], '', true);

                if (isset($aLinks['css'][0]) && isset($aLinks['js'][0])) {
                    $aLinks['css'] = $this->setUpArray($aLinks['css'][0]);
                    $aLinks['js'] = $this->setUpArray($aLinks['js'][0]);
                    $aLinksArray[] = $aLinks;
                }
            }

            $aReturnArray = [
                    'css' => $aLinksArray[0]['css'] ?: [],
                    'js'  => $aLinksArray[0]['js'] ?: []
            ];

            for ($i = 1; $i < count($aLinksArray); $i++) {
                $aReturnArray['css'] = array_filter(
                    array_intersect($aReturnArray['css'], $aLinksArray[$i]['css']),
                    function ($sUrl) {
                        return ! preg_match('#fonts\.googleapis\.com#i', $sUrl);
                    }
                );
                $aReturnArray['js'] = array_intersect($aReturnArray['js'], $aLinksArray[$i]['js']);
            }
        } catch (Exception $exception) {
            $this->logger->error((string)$exception);

            return new Json([
                'css' => [],
                'js' => []
            ]);
        }

        return new Json($aReturnArray);
    }

    protected function setUpArray($aLinks): array
    {
        return array_map(
            function ($sValue) {
                return preg_replace('#[?\#].*+#i', '', $sValue);
            },
            array_column($aLinks, 'url')
        );
    }
}
