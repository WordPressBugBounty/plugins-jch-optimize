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

use JchOptimize\Core\Admin\Icons;
use JchOptimize\Core\Input;
use JchOptimize\Core\Mvc\Controller;
use JchOptimize\View\MainHtml;

class Main extends Controller
{
    /**
     * @var MainHtml
     */
    private MainHtml $view;

    /**
     * @var Icons
     */
    private Icons $icons;

    public function __construct(MainHtml $view, Icons $icons, ?Input $input)
    {
        $this->view  = $view;
        $this->icons = $icons;

        parent::__construct($input);
    }

    public function execute(): bool
    {
        $this->view->setData([
                'tab'      => 'main',
                'icons'    => $this->icons
        ]);

        $this->view->loadResources();

        echo $this->view->render();

        return true;
    }
}
