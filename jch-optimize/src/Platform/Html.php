<?php
/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2020 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Platform;

defined('_WP_EXEC') or die('Restricted access');

use JchOptimize\Core\Admin\AbstractHtml;
use JchOptimize\Core\Exception;
use JchOptimize\Core\Uri\RequestOptions;
use JchOptimize\Core\Uri\Uri;
use JchOptimize\Core\Uri\UriComparator;
use JchOptimize\Core\Uri\Utils;

use function __;
use function array_merge;
use function array_slice;
use function get_nav_menu_locations;
use function get_registered_nav_menus;
use function home_url;
use function microtime;
use function parse_str;
use function rtrim;
use function wp_get_nav_menu_items;
use function wp_get_nav_menus;

class Html extends AbstractHtml
{
    public function getHomePageHtml(): string
    {
        JCH_DEBUG ? Profiler::mark('beforeGetHtml') : null;

        $url = home_url() . '/?jchbackend=1';

        try {


            $response = $this->http->get($url, ['Accept-Encoding' => 'identity;q=0']);

            if ($response->getStatusCode() != 200) {
                throw new Exception\RuntimeException(
                    Utility::translate(
                        'Failed fetching front end HTML with response code ' . $response->getStatusCode()
                    )
                );
            }

            JCH_DEBUG ? Profiler::mark('afterGetHtml') : null;


            $body = $response->getBody();
            //Set pointer to beginning of stream.
            $body->rewind();

            return $body->getContents();
        } catch (\Exception $e) {
            $this->logger->error($url . ': ' . $e->getMessage());

            JCH_DEBUG ? Profiler::mark('afterGetHtml)') : null;

            throw new Exception\RuntimeException(
                __(
                    'Load or refresh the front-end site first then refresh this page '
                        . 'to populate the multi select exclude lists.'
                )
            );
        }
    }

    public function getMainMenuItemsHtmls($iLimit = 5, $bIncludeUrls = false): array
    {
        $aMenuUrls = $this->getMenuUrls();
        $aMenuUrls = array_slice($aMenuUrls, 0, $iLimit);

        $aHtmls = [];

        //Limit the time spent on this
        $iTimer = microtime(true);

        foreach ($aMenuUrls as $menuUrl) {
            try {
                if ($bIncludeUrls) {
                    $aHtmls[] = [
                            'url'  => $menuUrl,
                            'html' => $this->getHtml($menuUrl)
                    ];
                } else {
                    $aHtmls[] = $this->getHtml($menuUrl);
                }
            } catch (Exception\ExceptionInterface $e) {
                $this->logger->error($e);
            }

            if (microtime(true) > $iTimer + 10.0) {
                break;
            }
        }

        return $aHtmls;
    }

    protected function getMenuUrls(): array
    {
        $homeUrl = rtrim(home_url(), '/\\');
        //Start array of oMenu urls with home page
        $aMenuUrls = [$homeUrl];

        $aMenus = wp_get_nav_menus();
        //If nothing just work with the home url
        if (! $aMenus) {
            return $aMenuUrls;
        }

        $locations      = get_registered_nav_menus();
        $aMenuLocations = get_nav_menu_locations();

        //Iterate through menus to find primary
        foreach ($aMenus as $oMenu) {
            if ($oMenu->term_id == $aMenuLocations['menu-primary']) {
                break;
            }
        }

        $aMenuItems = wp_get_nav_menu_items($oMenu);

        foreach ($aMenuItems as $oMenuItem) {
            if (rtrim($oMenuItem->url, '/\\') == $homeUrl) {
                continue;
            }

            if (! $cleanUrl = $this->cleanUrl($oMenuItem->url)) {
                continue;
            }

            if (!UriComparator::isCrossOrigin(Utils::uriFor($cleanUrl))) {
                $aMenuUrls[] = $cleanUrl;
            }
        }

        return $aMenuUrls;
    }

    private function cleanUrl(string $oMenuItem): string
    {
        $oUri = Utils::uriFor($oMenuItem);

        return (string)$oUri;
    }

    /**
     * @throws Exception\RuntimeException
     */
    protected function getHtml(string $url): string
    {
        $oUri   = Utils::uriFor($url);
        $sQuery = $oUri->getQuery();
        parse_str($sQuery, $aQuery);
        $aNewQuery = array_merge($aQuery, array('jchbackend' => '1'));
        $oUri = Uri::withQueryValues($oUri, $aNewQuery);

        $options = [
            RequestOptions::HEADERS => [
                'Accept-Enconding' => 'identity;q=0'
            ]
        ];

        $response = $this->http->get($oUri, $options);

        if ($response->getStatusCode() != 200) {
            throw new Exception\RuntimeException(
                'Failed fetching HTML: ' . $url . ' - Response code: ' . $response->getStatusCode()
            );
        }

        $body = $response->getBody();
        $body->rewind();

        return $body->getContents();
    }
}
