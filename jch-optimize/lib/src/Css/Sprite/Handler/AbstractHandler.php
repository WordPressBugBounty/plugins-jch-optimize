<?php

/**
 * @package     JchOptimize\Core\Css\Sprite\Handler
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace JchOptimize\Core\Css\Sprite\Handler;

use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\V91\Psr\Log\LoggerAwareTrait;
use JchOptimize\Core\Css\Sprite\HandlerInterface;
use JchOptimize\Core\Registry;

use function defined;

defined('_JCH_EXEC') or die('Restricted access');

abstract class AbstractHandler implements HandlerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public array $spriteFormats = [];

    public function __construct(protected Registry $params, protected array $options)
    {
    }
}
