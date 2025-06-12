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

namespace JchOptimize\WordPress\Controller;

use _JchOptimizeVendor\V91\Joomla\Input\Input;
use JchOptimize\Core\Mvc\Controller;
use JchOptimize\Core\Mvc\View;

class Help extends Controller
{
    public function __construct(private View $view, ?Input $input)
    {
        parent::__construct($input);
    }

    public function execute(): bool
    {
        $this->view->addData('tab', 'help');

        echo $this->view->render();

        return true;
    }
}
