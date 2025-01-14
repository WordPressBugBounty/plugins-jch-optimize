<?php

/**
 * Part of the Joomla Framework DI Package
 *
 * @copyright  Copyright (C) 2013 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */
namespace _JchOptimizeVendor\Joomla\DI;

/**
 * Defines the interface for a Container Aware class.
 *
 * @since  1.0
 */
interface ContainerAwareInterface
{
    /**
     * Set the DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  mixed
     *
     * @since   1.0
     */
    public function setContainer(Container $container);
}
