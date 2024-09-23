<?php

/**
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace JchOptimize\Core\Css\Sprite\Handler;

use _JchOptimizeVendor\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareTrait;
use JchOptimize\Core\Css\Sprite\HandlerInterface;
use JchOptimize\Core\Registry;

\defined('_JCH_EXEC') or exit('Restricted access');
abstract class AbstractHandler implements HandlerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public array $spriteFormats = [];

    protected Registry $params;

    protected array $options;

    public function __construct(Registry $params, array $options)
    {
        $this->params = $params;
        $this->options = $options;
    }
}
