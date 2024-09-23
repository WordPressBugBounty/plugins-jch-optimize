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

namespace JchOptimize\Controller;

use JchOptimize\Core\Input;
use JchOptimize\Core\Mvc\Controller;
use JchOptimize\Core\Mvc\View;

class Help extends Controller
{
    /**
     * @var View
     */
    private View $view;

    public function __construct(View $view, ?Input $input)
    {
        $this->view = $view;

        parent::__construct($input);
    }

    public function execute(): bool
    {
        $this->view->addData('tab', 'help');

        echo $this->view->render();

        return true;
    }
}
