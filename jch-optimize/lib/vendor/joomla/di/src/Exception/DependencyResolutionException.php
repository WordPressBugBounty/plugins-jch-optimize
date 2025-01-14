<?php

/**
 * Part of the Joomla Framework DI Package
 *
 * @copyright  Copyright (C) 2013 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */
namespace _JchOptimizeVendor\Joomla\DI\Exception;

use _JchOptimizeVendor\Psr\Container\ContainerExceptionInterface;
/**
 * Exception class for handling errors in resolving a dependency
 *
 * @since  1.0
 */
class DependencyResolutionException extends \RuntimeException implements ContainerExceptionInterface
{
}
