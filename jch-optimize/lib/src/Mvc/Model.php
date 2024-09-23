<?php

namespace JchOptimize\Core\Mvc;

use _JchOptimizeVendor\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\Joomla\Model\DatabaseModelInterface;
use _JchOptimizeVendor\Joomla\Model\DatabaseModelTrait;
use _JchOptimizeVendor\Joomla\Model\StatefulModelInterface;
use _JchOptimizeVendor\Joomla\Model\StatefulModelTrait;
use JchOptimize\Core\Container\ContainerAwareTrait;

class Model implements ContainerAwareInterface, DatabaseModelInterface, StatefulModelInterface
{
    use ContainerAwareTrait;
    use DatabaseModelTrait;
    use StatefulModelTrait;
}
