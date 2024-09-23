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

namespace JchOptimize;

use InvalidArgumentException;
use JchOptimize\Core\Container\Container;
use JchOptimize\Core\Container\ContainerAwareInterface;
use JchOptimize\Core\Container\ContainerAwareTrait;
use JchOptimize\Core\Input;

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
        $controller = $this->getController();

        if ($this->container->has($controller)) {
            call_user_func([$this->container->get($controller), 'execute']);
        } else {
            throw new InvalidArgumentException(sprintf('Cannot resolve controller aliased: %s', $controller));
        }
    }

    private function getController(): string
    {
        /** @var string|null $task */
        $task = $this->input->get('task');

        if (! is_null($task)) {
            return $task;
        }

        /** @var string|null $view */
        $view = $this->input->get('view');
        /** @var string|null $tab */
        $tab  = $this->input->get('tab');

        if (! is_null($tab) && is_null($view)) {
            return $tab;
        }

        if (! is_null($view)) {
            return $view;
        }

        return 'main';
    }
}
