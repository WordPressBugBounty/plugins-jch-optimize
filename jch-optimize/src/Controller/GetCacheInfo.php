<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Controller;

use JchOptimize\Core\Input;
use JchOptimize\Core\Mvc\Controller;
use JchOptimize\Model\Cache;

use function json_encode;

class GetCacheInfo extends Controller
{
    private Cache $cacheModel;

    public function __construct(Cache $cacheModel, ?Input $input)
    {
        $this->cacheModel = $cacheModel;

        parent::__construct($input);
    }

    public function execute(): bool
    {
        [$size, $numFiles] = $this->cacheModel->getCacheSize();

        $body = json_encode([
                'size'     => $size,
                'numFiles' => $numFiles
        ]);

        echo $body;

        return true;
    }
}