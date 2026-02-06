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

namespace JchOptimize\WordPress;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareTrait;
use _JchOptimizeVendor\V91\Joomla\Input\Input;
use InvalidArgumentException;
use JchOptimize\Core\Mvc\Controller;

use function call_user_func;
use function is_null;

class ControllerResolver implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    private Input $input;

    public function __construct(Container $container, Input $input)
    {
        $this->container = $container;
        $this->input = $input;
    }

    /**
     */
    public function resolve(): void
    {
        call_user_func([$this->getController(), 'execute']);
    }

    private function getControllerKey(): string
    {
        /** @var string|null $task */
        $task = $this->input->get('task');

        if (!is_null($task)) {
            return $task;
        }

        /** @var string|null $view */
        $view = $this->input->get('view');
        /** @var string|null $tab */
        $tab = $this->input->get('tab');

        if (!is_null($tab) && is_null($view)) {
            return $tab;
        }

        if (!is_null($view)) {
            return $view;
        }

        return 'main';
    }

    public function getController(): Controller
    {
        $key = $this->getControllerKey();

        if ($this->container->has($key)) {
            return $this->container->get($key);
        } else {
            throw new InvalidArgumentException(sprintf('Cannot resolve controller aliased: %s', $key));
        }
    }
}
