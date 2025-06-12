<?php

namespace JchOptimize\Core\Mvc;

use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\V91\Joomla\DI\ContainerAwareTrait;
use _JchOptimizeVendor\V91\Joomla\Model\DatabaseModelInterface;
use _JchOptimizeVendor\V91\Joomla\Model\DatabaseModelTrait;
use _JchOptimizeVendor\V91\Joomla\Model\StatefulModelInterface;
use _JchOptimizeVendor\V91\Joomla\Model\StatefulModelTrait;

class Model implements ContainerAwareInterface, DatabaseModelInterface, StatefulModelInterface
{
    use ContainerAwareTrait;
    use DatabaseModelTrait;
    use StatefulModelTrait;
}
