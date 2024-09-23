<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2021 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Controller;

use JchOptimize\Core\Input;
use JchOptimize\Core\Mvc\Controller;
use JchOptimize\Log\WordpressNoticeLogger;
use JchOptimize\Plugin\Loader;

use function __;
use function wp_redirect;

class OrderPlugins extends Controller
{
    /**
     * @var Loader
     */
    private Loader $loader;

    public function __construct(Loader $loader, ?Input $input = null)
    {
        $this->loader = $loader;

        parent::__construct($input);
    }

    public function execute(): bool
    {
        $this->loader->orderPlugin();
        /** @var WordpressNoticeLogger $logger */
        $logger = $this->logger;
        $logger->success(__('Plugins ordered successfully', 'jch-optimize'));

        wp_redirect('options-general.php?page=jch_optimize');

        return true;
    }
}
