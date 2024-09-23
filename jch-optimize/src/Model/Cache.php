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

namespace JchOptimize\Model;

use JchOptimize\Core\Model\CacheModelTrait;
use JchOptimize\Core\Mvc\Model;
use JchOptimize\Core\PageCache\PageCache;

class Cache extends Model
{
    use CacheModelTrait;

    /**
     * @var PageCache
     */
    private PageCache $pageCache;

    public function __construct(PageCache $pageCache)
    {
        $this->pageCache        = $pageCache;
    }
}
