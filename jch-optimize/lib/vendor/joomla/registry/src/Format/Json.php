<?php

/**
 * Part of the Joomla Framework Registry Package
 *
 * @copyright  Copyright (C) 2013 Open Source Matters, Inc.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */
namespace _JchOptimizeVendor\Joomla\Registry\Format;

use _JchOptimizeVendor\Joomla\Registry\Factory;
use _JchOptimizeVendor\Joomla\Registry\FormatInterface;
use RuntimeException;
/**
 * JSON format handler for Registry.
 *
 * @since  1.0.0
 */
class Json implements FormatInterface
{
    /**
     * Converts an object into a JSON formatted string.
     *
     * @param  object  $object   Data source object.
     * @param  array   $options  Options used by the formatter.
     *
     * @return  string  JSON formatted string.
     *
     * @since   1.0.0
     */
    public function objectToString($object, array $options = [])
    {
        $bitMask = $options['bitmask'] ?? 0;
        $depth = $options['depth'] ?? 512;
        return \json_encode($object, $bitMask, $depth);
    }
    /**
     * Parse a JSON formatted string and convert it into an object.
     *
     * If the string is not in JSON format, this method will attempt to parse it as INI format.
     *
     * @param  string  $data     JSON formatted string to convert.
     * @param  array   $options  Options used by the formatter.
     *
     * @return  object   Data object.
     *
     * @throws  \RuntimeException
     * @since   1.0.0
     */
    public function stringToObject($data, array $options = ['processSections' => \false])
    {
        $data = \trim($data);
        if (empty($data)) {
            return new \stdClass();
        }
        $decoded = \json_decode($data);
        // Check for an error decoding the data
        if ($decoded === null && \json_last_error() !== \JSON_ERROR_NONE) {
            // If it's an ini file, parse as ini.
            if ($data !== '' && $data[0] !== '{') {
                return Factory::getFormat('Ini')->stringToObject($data, $options);
            }
            throw new \RuntimeException(\sprintf('Error decoding JSON data: %s', \json_last_error_msg()));
        }
        return (object) $decoded;
    }
}
