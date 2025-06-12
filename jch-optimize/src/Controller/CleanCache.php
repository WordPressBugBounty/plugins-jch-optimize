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

namespace JchOptimize\WordPress\Controller;

use _JchOptimizeVendor\V91\Joomla\Input\Input;
use JchOptimize\Core\Model\CacheMaintainer;
use JchOptimize\Core\Mvc\Controller;
use JchOptimize\WordPress\Log\WordpressNoticeLogger;

use function __;
use function JchOptimize\WordPress\base64_decode_url;
use function wp_redirect;

class CleanCache extends Controller
{
    public function __construct(private CacheMaintainer $cacheMaintainer, ?Input $input)
    {
        parent::__construct($input);
    }

    public function execute(): bool
    {
        /** @var WordpressNoticeLogger $logger */
        $logger = $this->logger;
        /** @var Input $input */
        $input = $this->getInput();

        if ($this->cacheMaintainer->cleanCache()) {
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
