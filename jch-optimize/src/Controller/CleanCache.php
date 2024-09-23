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
use JchOptimize\Model\Cache;

use function __;
use function JchOptimize\base64_decode_url;
use function wp_redirect;

class CleanCache extends Controller
{
    /**
     * @var Cache
     */
    private Cache $model;

    public function __construct(Cache $model, ?Input $input)
    {
        $this->model = $model;

        parent::__construct($input);
    }

    public function execute(): bool
    {
        /** @var WordpressNoticeLogger $logger */
        $logger = $this->logger;
        /** @var Input $input */
        $input = $this->getInput();

        if ($this->model->cleanCache()) {
            $logger->success(__('Cache deleted successfully!', 'jch-optimize'));

            $result = true;
        } else {
            $logger->error(__('Error cleaning cache!', 'jch-optimize'));

            $result = false;
        }

        if (($return = (string)$input->get('return')) != '') {
            $redirect = base64_decode_url($return);
        } else {
            $redirect = 'options-general.php?page=jch_optimize';
        }

        wp_redirect($redirect);

        return $result;
    }
}
