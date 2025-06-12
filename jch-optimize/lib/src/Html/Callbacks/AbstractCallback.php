<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Html\Callbacks;

use _JchOptimizeVendor\V91\Joomla\DI\Container;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareTrait;
use JchOptimize\Core\Exception\PregErrorException;
use JchOptimize\Core\Html\CallbackInterface;
use JchOptimize\Core\Html\HtmlElementBuilder;
use JchOptimize\Core\Html\HtmlElementInterface;
use JchOptimize\Core\Registry;

use function defined;
use function trim;

defined('_JCH_EXEC') or die('Restricted access');

abstract class AbstractCallback implements ContainerAwareInterface, CallbackInterface
{
    use ContainerAwareTrait;

    /**
     * @var string        RegEx used to process HTML
     */
    protected string $regex = '';

    public function __construct(Container $container, protected Registry $params)
    {
        $this->container = $container;
    }

    public function setRegex(string $regex): void
    {
        $this->regex = $regex;
    }

    public function processMatches(array $matches): string
    {
        if (trim($matches[0]) === '') {
            return $matches[0];
        }

        if (str_starts_with($matches[0], '<!--')) {
            return $matches[0];
        }

        try {
            $element = HtmlElementBuilder::load($matches[0]);
        } catch (PregErrorException) {
            return $matches[0];
        }

        return $this->internalProcessMatches($element);
    }

    abstract protected function internalProcessMatches(HtmlElementInterface $element);
}
