<?php

/**
 * Part of the Joomla Framework Registry Package
 *
 * @copyright  Copyright (C) 2015 Open Source Matters, Inc.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */
namespace _JchOptimizeVendor\Joomla\Registry;

/**
 * Factory class to fetch Registry objects
 *
 * @since  1.5.0
 */
class Factory
{
    /**
     * Returns a FormatInterface object, only creating it if it doesn't already exist.
     *
     * @param  string  $type     The format to load
     * @param  array   $options  Additional options to configure the object
     *
     * @return  FormatInterface  Registry format handler
     *
     * @throws  \InvalidArgumentException
     *
     * @since   1.5.0
     * @since   2.0.0  Object caching is no longer supported. `Factory::getFormat()` returns a new `FormatInterface`
     *          instance on each call.
     */
    public static function getFormat($type, array $options = [])
    {
        // Sanitize format type.
        $type = \strtolower(\preg_replace('/[^A-Z\\d_]/i', '', $type));
        $localNamespace = __NAMESPACE__ . '\\Format';
        $namespace = $options['format_namespace'] ?? $localNamespace;
        $class = $namespace . '\\' . \ucfirst($type);
        if (!\class_exists($class)) {
            // Were we given a custom namespace?  If not, there's nothing else we can do
            if ($namespace === $localNamespace) {
                throw new \InvalidArgumentException(\sprintf('Unable to load format class for type "%s".', $type), 500);
            }
            $class = $localNamespace . '\\' . \ucfirst($type);
            if (!\class_exists($class)) {
                throw new \InvalidArgumentException(\sprintf('Unable to load format class for type "%s".', $type), 500);
            }
        }
        return new $class();
    }
}
