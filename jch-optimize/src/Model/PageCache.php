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

namespace JchOptimize\Model;

use Exception;
use JchOptimize\Core\Container\Container;
use JchOptimize\Core\Input;
use JchOptimize\Core\Mvc\Model;
use JchOptimize\Core\PageCache\CaptureCache;
use JchOptimize\Core\PageCache\PageCache as CorePageCache;
use JchOptimize\Core\Registry;

use function get_transient;
use function is_array;
use function is_null;
use function set_transient;

class PageCache extends Model
{
    /**
     * name of currently used cache adapter
     *
     * @var string
     */
    private string $adapter;
    /**
     * @var CorePageCache
     */
    private CorePageCache $pageCache;

    public function __construct(Input $input, CorePageCache $pageCache, Container $container)
    {
        $this->pageCache = $pageCache;
        $this->setContainer($container);

        if (JCH_PRO && $pageCache instanceof CaptureCache) {
            /** @see CaptureCache::updateHtaccess() */
            $this->getContainer()->get(CaptureCache::class)->updateHtaccess();
        }

        try {
            $registry = $this->populateRegistryFromRequest($input, ['filter', 'list']);
        } catch (Exception $e) {
            $registry = new Registry();
        }

        $this->setState($registry);
    }

    /**
     * @param Input $input
     * @param string[] $keys
     * @return Registry
     */
    private function populateRegistryFromRequest(Input $input, array $keys): Registry
    {
        $data = new Registry();

        foreach ($keys as $key) {
            //Check for value from input first
            /** @var string[]|null $requestKey */
            $requestKey = $input->get($key);

            if (is_null($requestKey)) {
                //Not found, let's check the transients
                /** @var string[]|null $requestKey */
                $requestKey = get_transient('jch_optimize_state_' . $key);
            }

            //If we've got one by now let's set it in registry
            if (! empty($requestKey) && is_array($requestKey)) {
                foreach ($requestKey as $requestName => $requestValue) {
                    if (! empty($requestValue) || $requestValue == '0') {
                        $data->set($key . '_' . $requestName, $requestValue);
                    }
                }

                //Let's save this one in the transient
                set_transient('jch_optimize_state_' . $key, $requestKey, 300);
            }
        }

        return $data;
    }

    public function getItems(): array
    {
        $filters = [
            'time-1',
            'time-2',
            'search',
            'device',
            'adapter',
            'http-request'
        ];

        foreach ($filters as $filter) {
            /** @var string|null $filterState */
            $filterState = $this->getState()->get("filter_{$filter}");

            if (! empty($filterState)) {
                $this->pageCache->setFilter("filter_{$filter}", $filterState);
            }
        }

        /** @var string|null $fullOrderingList */
        $fullOrderingList = $this->getState()->get('list_fullordering');

        if (! empty($fullOrderingList)) {
            $this->pageCache->setList('list_fullordering', str_replace('_', ' ', $fullOrderingList));
        }

        return $this->pageCache->getItems();
    }

    public function delete(array $ids): bool
    {
        return $this->pageCache->deleteItemsByIds($ids);
    }

    public function deleteAll(): bool
    {
        return $this->pageCache->deleteAllItems();
    }

    public function getAdapterName(): string
    {
        return $this->pageCache->getAdapterName();
    }

    public function isCaptureCacheEnabled(): bool
    {
        return $this->pageCache->isCaptureCacheEnabled();
    }
}
