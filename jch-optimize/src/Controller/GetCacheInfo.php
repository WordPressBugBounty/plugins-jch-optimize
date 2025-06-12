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

namespace JchOptimize\WordPress\Controller;

use _JchOptimizeVendor\V91\Joomla\Input\Input;
use JchOptimize\Core\Model\CacheMaintainer;
use JchOptimize\Core\Mvc\Controller;

use function json_encode;

class GetCacheInfo extends Controller
{
    public function __construct(private CacheMaintainer $cacheMaintainer, ?Input $input)
    {
        parent::__construct($input);
    }

    public function execute(): bool
    {
        [$size, $numFiles] = $this->cacheMaintainer->getCacheSize();

        $body = json_encode([
                'size'     => $size,
                'numFiles' => $numFiles
        ]);

        echo $body;

        return true;
    }
}
