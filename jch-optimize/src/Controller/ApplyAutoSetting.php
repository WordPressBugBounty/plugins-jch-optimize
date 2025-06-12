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
use JchOptimize\Core\Mvc\Controller;
use JchOptimize\WordPress\Model\Configure;

use function json_encode;

class ApplyAutoSetting extends Controller
{
    public function __construct(private Configure $model, ?Input $input)
    {
        parent::__construct($input);
    }

    public function execute(): bool
    {
        /** @var Input $input */
        $input = $this->getInput();
        $setting = (string)$input->get('autosetting', 's1');

        if (!check_ajax_referer($setting, false, false)) {
            echo json_encode(['success' => false]);
            return false;
        }

        $this->model->applyAutoSetting($setting);

        echo json_encode(['success' => true]);

        return true;
    }
}
