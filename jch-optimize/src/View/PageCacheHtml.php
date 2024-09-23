<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\View;

use JchOptimize\Core\Mvc\View;
use JchOptimize\Core\Registry;
use JchOptimize\Html\Helper;

use function wp_add_inline_script;

class PageCacheHtml extends View
{
    public function renderStatefulElements(Registry $state): void
    {
        //Generate HTML for Search Input box
        $filterSearchState = (string)$state->get('filter_search');
        //language=HTML
        $searchInput = '<input type="text" id="filter_search" name="filter[search]" class="form-control" value="' . $filterSearchState . '" placeholder="Search by URL" aria-label="Search by URL" aria-describedby="filter-search-button">';

        $this->addData('searchInput', $searchInput);

        //Generate HTML for filter time 1
        $filterTime1Options     = [
                ''        => '- Filter by Start Time -',
                '900'     => '>= 15 mins ago',
                '1800'    => '>= 30 mins ago ',
                '3600'    => '>= 1 hour ago',
                '10800'   => '>= 3 hours ago',
                '21600'   => '>= 6 hours ago',
                '43200'   => '>= 12 hours ago',
                '86400'   => '>= 1 day ago',
                '172800'  => '>= 2 days ago',
                '604800'  => '>= 1 week ago',
                '1209600' => '>= 2 weeks ago'
        ];
        /** @var string|null $filterTime1State */
        $filterTime1State = $state->get('filter_time-1');
        $filterTime1OptionsHtml = Helper::option($filterTime1Options, $filterTime1State);
        $filterTime1SelectHtml  = $this->selectListGenerator('filter', $filterTime1OptionsHtml, 'time-1');
        $this->addData('filterTime1SelectHtml', $filterTime1SelectHtml);

        //Generate HTML for filter time 2
        $filterTime2Options     = [
                ''        => '- Filter by End Time -',
                '900'     => '< 15 mins ago',
                '1800'    => '< 30 mins ago',
                '3600'    => '< 1 hour ago',
                '10800'   => '< 3 hours ago',
                '21600'   => '< 6 hours ago',
                '43200'   => '< 12 hours ago',
                '86400'   => '< 1 day ago',
                '172800'  => '< 2 days ago',
                '604800'  => '< 1 week ago',
                '1209600' => '< 2 weeks ago'
        ];
        /** @var string|null $filterTime2State */
        $filterTime2State = $state->get('filter_time-2');
        $filterTime2OptionsHtml = Helper::option($filterTime2Options, $filterTime2State);
        $filterTime2SelectHtml  = $this->selectListGenerator('filter', $filterTime2OptionsHtml, 'time-2');
        $this->addData('filterTime2SelectHtml', $filterTime2SelectHtml);

        //Generate HTML for filter device
        $deviceOptions = [
                ''        => '- Filter by device -',
                'Mobile'  => 'Mobile',
                'Desktop' => 'Desktop'
        ];
        /** @var string|null $filterDeviceState */
        $filterDeviceState = $state->get('filter_device');
        $filterDeviceOptionsHtml = Helper::option($deviceOptions, $filterDeviceState);
        $filterDeviceSelectHtml  = $this->selectListGenerator('filter', $filterDeviceOptionsHtml, 'device');
        $this->addData('filterDeviceSelectHtml', $filterDeviceSelectHtml);

        //Generate HTML for filter adapter
        $filterAdapterOptions     = [
                ''           => '- Filter by Adapter -',
                'Filesystem' => 'Filesystem',
                'Redis'      => 'Redis',
                'Apcu'       => 'APCu',
                'Memcached'  => 'Memcached',
                'Wincache'   => 'Wincache'
        ];
        /** @var string|null $filterAdapterState */
        $filterAdapterState = $state->get('filter_adapter');
        $filterAdapterOptionsHtml = Helper::option($filterAdapterOptions, $filterAdapterState);
        $filterAdapterSelectHtml  = $this->selectListGenerator('filter', $filterAdapterOptionsHtml, 'adapter');
        $this->addData('filterAdapterSelectHtml', $filterAdapterSelectHtml);

        //Generate HTML for filter HTTP Request
        $filterHttpRequestOptions     = [
                ''    => '- Filter by HTTP Request -',
                'yes' => 'Yes',
                'no'  => 'No'
        ];
        /** @var string|null $filterHttpRequestState */
        $filterHttpRequestState = $state->get('filter_http-request');
        $filterHttpRequestOptionsHtml = Helper::option(
            $filterHttpRequestOptions,
            $filterHttpRequestState
        );
        $filterHttpRequestSelectHtml  = $this->selectListGenerator(
            'filter',
            $filterHttpRequestOptionsHtml,
            'http-request'
        );
        $this->addData('filterHttpRequestSelectHtml', $filterHttpRequestSelectHtml);

        //Generate option value for ordering list
        $orderOptions = [
                ''           => 'Sort Table By:',
                'mtime_ASC'  => 'Last modified time ascending',
                'mtime_DESC' => 'Last modified time descending',
                'url_ASC'    => 'Page URL ascending',
                'url_DESC'   => 'Page URL descending',
        ];
        /** @var string|null $listFullOrderingState */
        $listFullOrderingState = $state->get('list_fullordering');
        $orderOptionsHtml = Helper::option($orderOptions, $listFullOrderingState);

        $this->addData('orderOptionsHtml', $orderOptionsHtml);

        //Generate option values for limit list
        $limitOptions = [
                '5'   => '5',
                '10'  => '10',
                '15'  => '15',
                '20'  => '20',
                '25'  => '25',
                '30'  => '30',
                '50'  => '50',
                '100' => '100',
                '200' => '200',
                '500' => '500',
                '-1'  => 'All'
        ];
        /** @var string $listLimitState */
        $listLimitState = $state->get('list_limit', '20');
        $limitOptionsHtml = Helper::option($limitOptions, $listLimitState);

        $this->addData('limitOptionsHtml', $limitOptionsHtml);
    }

    private function selectListGenerator(string $type, string $optionsHtml, string $value): string
    {
        return <<<HTML
<select id="{$type}_{$value}" name="{$type}[{$value}]" class="ms-2" onchange="this.form.submit();"
                aria-label="{$type} {$value}">
            {$optionsHtml}
        </select>
HTML;
    }

    public function loadResources(): void
    {
        $js = <<<JS
document.addEventListener("DOMContentLoaded", function(){
	var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
	var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
  		return new bootstrap.Tooltip(tooltipTriggerEl,{
      			placement: 'right'
  		})
	})
});
JS;
        wp_add_inline_script('jch-bootstrap-js', $js);
    }
}
