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

namespace JchOptimize\Core\Interfaces;

\defined('_JCH_EXEC') or exit('Restricted access');
interface Html
{
    /**
     * Returns HTML of the front page.
     */
    public function getHomePageHtml(): string;

    /**
     * Returns an array of all the html of the page on the main menu.
     *
     * @param mixed $iLimit
     * @param mixed $bIncludeUrls
     */
    public function getMainMenuItemsHtmls($iLimit = 5, $bIncludeUrls = \false): array;
}
