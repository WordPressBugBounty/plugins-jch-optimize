<?php

namespace JchOptimize\Core\Mvc;

use _JchOptimizeVendor\Joomla\Controller\AbstractController;
use _JchOptimizeVendor\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareTrait;
use JchOptimize\Core\Container\ContainerAwareTrait;

abstract class Controller extends AbstractController implements ContainerAwareInterface, LoggerAwareInterface
{
    use ContainerAwareTrait;
    use LoggerAwareTrait;

    abstract public function execute();
}
