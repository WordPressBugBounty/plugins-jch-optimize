<?php

/**
 * Part of the Joomla Framework DI Package
 *
 * @copyright  Copyright (C) 2013 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */
namespace _JchOptimizeVendor\Joomla\DI\Exception;

use _JchOptimizeVendor\Psr\Container\NotFoundExceptionInterface;
/**
 * No entry was found in the container.
 *
 * @since  1.5.0
 */
class KeyNotFoundException extends \InvalidArgumentException implements NotFoundExceptionInterface
{
}
