<?php

/**
 * Part of the Joomla Framework Utilities Package
 *
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */
namespace _JchOptimizeVendor\Joomla\Utilities;

use _JchOptimizeVendor\Joomla\String\StringHelper;
/**
 * ArrayHelper is an array utility class for doing all sorts of odds and ends with arrays.
 *
 * @since  1.0
 */
final class ArrayHelper
{
    /**
     * Private constructor to prevent instantiation of this class
     *
     * @since   1.0
     */
    private function __construct()
    {
    }
    /**
     * Function to convert array to integer values
     *
     * @param   array      $array    The source array to convert
     * @param   int|array  $default  A default value to assign if $array is not an array
     *
     * @return  array
     *
     * @since   1.0
     */
    public static function toInteger($array, $default = null)
    {
        if (\is_array($array)) {
            return \array_map('intval', $array);
        }
        if ($default === null) {
            return [];
        }
        if (\is_array($default)) {
            return static::toInteger($default, null);
        }
        return [(int) $default];
    }
    /**
     * Utility function to map an array to a stdClass object.
     *
     * @param   array    $array      The array to map.
     * @param   string   $class      Name of the class to create
     * @param   boolean  $recursive  Convert also any array inside the main array
     *
     * @return  object
     *
     * @since   1.0
     */
    public static function toObject(array $array, $class = 'stdClass', $recursive = \true)
    {
        $obj = new $class();
        foreach ($array as $k => $v) {
            if ($recursive && \is_array($v)) {
                $obj->{$k} = static::toObject($v, $class);
            } else {
                $obj->{$k} = $v;
            }
        }
        return $obj;
    }
    /**
     * Utility function to map an array to a string.
     *
     * @param   array    $array         The array to map.
     * @param   string   $innerGlue     The glue (optional, defaults to '=') between the key and the value.
     * @param   string   $outerGlue     The glue (optional, defaults to ' ') between array elements.
     * @param   boolean  $keepOuterKey  True if final key should be kept.
     *
     * @return  string
     *
     * @since   1.0
     */
    public static function toString(array $array, $innerGlue = '=', $outerGlue = ' ', $keepOuterKey = \false)
    {
        $output = [];
        foreach ($array as $key => $item) {
            if (\is_array($item)) {
                if ($keepOuterKey) {
                    $output[] = $key;
                }
                // This is value is an array, go and do it again!
                $output[] = static::toString($item, $innerGlue, $outerGlue, $keepOuterKey);
            } else {
                $output[] = $key . $innerGlue . '"' . $item . '"';
            }
        }
        return \implode($outerGlue, $output);
    }
    /**
     * Utility function to map an object to an array
     *
     * @param   object   $source   The source object
     * @param   boolean  $recurse  True to recurse through multi-level objects
     * @param   string   $regex    An optional regular expression to match on field names
     *
     * @return  array
     *
     * @since   1.0
     */
    public static function fromObject($source, $recurse = \true, $regex = null)
    {
        if (\is_object($source) || \is_array($source)) {
            return self::arrayFromObject($source, $recurse, $regex);
        }
        return [];
    }
    /**
     * Utility function to map an object or array to an array
     *
     * @param   mixed    $item     The source object or array
     * @param   boolean  $recurse  True to recurse through multi-level objects
     * @param   string   $regex    An optional regular expression to match on field names
     *
     * @return  array
     *
     * @since   1.0
     */
    private static function arrayFromObject($item, $recurse, $regex)
    {
        if (\is_object($item)) {
            $result = [];
            foreach (\get_object_vars($item) as $k => $v) {
                if (!$regex || \preg_match($regex, $k)) {
                    if ($recurse) {
                        $result[$k] = self::arrayFromObject($v, $recurse, $regex);
                    } else {
                        $result[$k] = $v;
                    }
                }
            }
            return $result;
        }
        if (\is_array($item)) {
            $result = [];
            foreach ($item as $k => $v) {
                $result[$k] = self::arrayFromObject($v, $recurse, $regex);
            }
            return $result;
        }
        return $item;
    }
    /**
     * Adds a column to an array of arrays or objects
     *
     * @param   array   $array    The source array
     * @param   array   $column   The array to be used as new column
     * @param   string  $colName  The index of the new column or name of the new object property
     * @param   string  $keyCol   The index of the column or name of object property to be used for mapping with the new column
     *
     * @return  array  An array with the new column added to the source array
     *
     * @since   1.5.0
     * @see     https://www.php.net/manual/en/language.types.array.php
     */
    public static function addColumn(array $array, array $column, $colName, $keyCol = null)
    {
        $result = [];
        foreach ($array as $i => $item) {
            $value = null;
            if (!isset($keyCol)) {
                $value = static::getValue($column, $i);
            } else {
                // Convert object to array
                $subject = \is_object($item) ? static::fromObject($item) : $item;
                if (isset($subject[$keyCol]) && \is_scalar($subject[$keyCol])) {
                    $value = static::getValue($column, $subject[$keyCol]);
                }
            }
            // Add the column
            if (\is_object($item)) {
                if (isset($colName)) {
                    $item->{$colName} = $value;
                }
            } else {
                if (isset($colName)) {
                    $item[$colName] = $value;
                } else {
                    $item[] = $value;
                }
            }
            $result[$i] = $item;
        }
        return $result;
    }
    /**
     * Remove a column from an array of arrays or objects
     *
     * @param   array   $array    The source array
     * @param   string  $colName  The index of the column or name of object property to be removed
     *
     * @return  array  Column of values from the source array
     *
     * @since   1.5.0
     * @see     https://www.php.net/manual/en/language.types.array.php
     */
    public static function dropColumn(array $array, $colName)
    {
        $result = [];
        foreach ($array as $i => $item) {
            if (\is_object($item) && isset($item->{$colName})) {
                unset($item->{$colName});
            } elseif (\is_array($item) && isset($item[$colName])) {
                unset($item[$colName]);
            }
            $result[$i] = $item;
        }
        return $result;
    }
    /**
     * Extracts a column from an array of arrays or objects
     *
     * @param   array   $array     The source array
     * @param   string  $valueCol  The index of the column or name of object property to be used as value
     *                             It may also be NULL to return complete arrays or objects (this is
     *                             useful together with <var>$keyCol</var> to reindex the array).
     * @param   string  $keyCol    The index of the column or name of object property to be used as key
     *
     * @return  array  Column of values from the source array
     *
     * @since   1.0
     * @see     https://www.php.net/manual/en/language.types.array.php
     * @see     https://www.php.net/manual/en/function.array-column.php
     */
    public static function getColumn(array $array, $valueCol, $keyCol = null)
    {
        return \array_reduce($array, function ($result, $item) use($keyCol, $valueCol) {
            $array = \is_object($item) ? \get_object_vars($item) : $item;
            if ($valueCol === null) {
                $value = $item;
            } else {
                if (!\array_key_exists($valueCol, $array)) {
                    return $result;
                }
                $value = $array[$valueCol];
            }
            if ($keyCol !== null && \array_key_exists($keyCol, $array) && \is_scalar($array[$keyCol])) {
                $result[$array[$keyCol]] = $value;
            } else {
                $result[] = $value;
            }
            return $result;
        }, []);
    }
    /**
     * Utility function to return a value from a named array or a specified default
     *
     * @param   array|\ArrayAccess  $array    A named array or object that implements ArrayAccess
     * @param   string              $name     The key to search for (this can be an array index or a dot separated key sequence as in Registry)
     * @param   mixed               $default  The default value to give if no key found
     * @param   string              $type     Return type for the variable (INT, FLOAT, STRING, WORD, BOOLEAN, ARRAY)
     *
     * @return  mixed
     *
     * @since   1.0
     * @throws  \InvalidArgumentException
     */
    public static function getValue($array, $name, $default = null, $type = '')
    {
        if (!\is_array($array) && !$array instanceof \ArrayAccess) {
            throw new \InvalidArgumentException('The object must be an array or an object that implements ArrayAccess');
        }
        $result = null;
        if (isset($array[$name])) {
            $result = $array[$name];
        } elseif (\strpos($name, '.')) {
            list($name, $subset) = \explode('.', $name, 2);
            if (isset($array[$name]) && \is_array($array[$name])) {
                return static::getValue($array[$name], $subset, $default, $type);
            }
        }
        // Handle the default case
        if ($result === null) {
            $result = $default;
        }
        // Handle the type constraint
        switch (\strtoupper($type)) {
            case 'INT':
            case 'INTEGER':
                // Only use the first integer value
                @\preg_match('/-?[0-9]+/', $result, $matches);
                $result = @(int) $matches[0];
                break;
            case 'FLOAT':
            case 'DOUBLE':
                // Only use the first floating point value
                @\preg_match('/-?[0-9]+(\\.[0-9]+)?/', $result, $matches);
                $result = @(float) $matches[0];
                break;
            case 'BOOL':
            case 'BOOLEAN':
                $result = (bool) $result;
                break;
            case 'ARRAY':
                if (!\is_array($result)) {
                    $result = [$result];
                }
                break;
            case 'STRING':
                $result = (string) $result;
                break;
            case 'WORD':
                $result = (string) \preg_replace('#\\W#', '', $result);
                break;
            case 'NONE':
            default:
                // No casting necessary
                break;
        }
        return $result;
    }
    /**
     * Takes an associative array of arrays and inverts the array keys to values using the array values as keys.
     *
     * Example:
     * $input = array(
     *     'New' => array('1000', '1500', '1750'),
     *     'Used' => array('3000', '4000', '5000', '6000')
     * );
     * $output = ArrayHelper::invert($input);
     *
     * Output would be equal to:
     * $output = array(
     *     '1000' => 'New',
     *     '1500' => 'New',
     *     '1750' => 'New',
     *     '3000' => 'Used',
     *     '4000' => 'Used',
     *     '5000' => 'Used',
     *     '6000' => 'Used'
     * );
     *
     * @param   array  $array  The source array.
     *
     * @return  array
     *
     * @since   1.0
     */
    public static function invert(array $array)
    {
        $return = [];
        foreach ($array as $base => $values) {
            if (!\is_array($values)) {
                continue;
            }
            foreach ($values as $key) {
                // If the key isn't scalar then ignore it.
                if (\is_scalar($key)) {
                    $return[$key] = $base;
                }
            }
        }
        return $return;
    }
    /**
     * Method to determine if an array is an associative array.
     *
     * @param   array  $array  An array to test.
     *
     * @return  boolean
     *
     * @since   1.0
     */
    public static function isAssociative($array)
    {
        if (\is_array($array)) {
            foreach (\array_keys($array) as $k => $v) {
                if ($k !== $v) {
                    return \true;
                }
            }
        }
        return \false;
    }
    /**
     * Pivots an array to create a reverse lookup of an array of scalars, arrays or objects.
     *
     * @param   array   $source  The source array.
     * @param   string  $key     Where the elements of the source array are objects or arrays, the key to pivot on.
     *
     * @return  array  An array of arrays pivoted either on the value of the keys, or an individual key of an object or array.
     *
     * @since   1.0
     */
    public static function pivot(array $source, $key = null)
    {
        $result = [];
        $counter = [];
        foreach ($source as $index => $value) {
            // Determine the name of the pivot key, and its value.
            if (\is_array($value)) {
                // If the key does not exist, ignore it.
                if (!isset($value[$key])) {
                    continue;
                }
                $resultKey = $value[$key];
                $resultValue = $source[$index];
            } elseif (\is_object($value)) {
                // If the key does not exist, ignore it.
                if (!isset($value->{$key})) {
                    continue;
                }
                $resultKey = $value->{$key};
                $resultValue = $source[$index];
            } else {
                // Just a scalar value.
                $resultKey = $value;
                $resultValue = $index;
            }
            // The counter tracks how many times a key has been used.
            if (empty($counter[$resultKey])) {
                // The first time around we just assign the value to the key.
                $result[$resultKey] = $resultValue;
                $counter[$resultKey] = 1;
            } elseif ($counter[$resultKey] == 1) {
                // If there is a second time, we convert the value into an array.
                $result[$resultKey] = [$result[$resultKey], $resultValue];
                $counter[$resultKey]++;
            } else {
                // After the second time, no need to track any more. Just append to the existing array.
                $result[$resultKey][] = $resultValue;
            }
        }
        unset($counter);
        return $result;
    }
    /**
     * Utility function to sort an array of objects on a given field
     *
     * @param   array  $a              An array of objects
     * @param   mixed  $k              The key (string) or an array of keys to sort on
     * @param   mixed  $direction      Direction (integer) or an array of direction to sort in [1 = Ascending] [-1 = Descending]
     * @param   mixed  $caseSensitive  Boolean or array of booleans to let sort occur case sensitive or insensitive
     * @param   mixed  $locale         Boolean or array of booleans to let sort occur using the locale language or not
     *
     * @return  array
     *
     * @since   1.0
     */
    public static function sortObjects(array $a, $k, $direction = 1, $caseSensitive = \true, $locale = \false)
    {
        if (!\is_array($locale) || !\is_array($locale[0])) {
            $locale = [$locale];
        }
        $sortCase = (array) $caseSensitive;
        $sortDirection = (array) $direction;
        $key = (array) $k;
        $sortLocale = $locale;
        \usort($a, function ($a, $b) use($sortCase, $sortDirection, $key, $sortLocale) {
            for ($i = 0, $count = \count($key); $i < $count; $i++) {
                if (isset($sortDirection[$i])) {
                    $direction = $sortDirection[$i];
                }
                if (isset($sortCase[$i])) {
                    $caseSensitive = $sortCase[$i];
                }
                if (isset($sortLocale[$i])) {
                    $locale = $sortLocale[$i];
                }
                $va = $a->{$key[$i]};
                $vb = $b->{$key[$i]};
                if ((\is_bool($va) || \is_numeric($va)) && (\is_bool($vb) || \is_numeric($vb))) {
                    $cmp = $va - $vb;
                } elseif ($caseSensitive) {
                    $cmp = StringHelper::strcmp($va, $vb, $locale);
                } else {
                    $cmp = StringHelper::strcasecmp($va, $vb, $locale);
                }
                if ($cmp > 0) {
                    return $direction;
                }
                if ($cmp < 0) {
                    return -$direction;
                }
            }
            return 0;
        });
        return $a;
    }
    /**
     * Multidimensional array safe unique test
     *
     * @param   array  $array  The array to make unique.
     *
     * @return  array
     *
     * @see     https://www.php.net/manual/en/function.array-unique.php
     * @since   1.0
     */
    public static function arrayUnique(array $array)
    {
        $array = \array_map('serialize', $array);
        $array = \array_unique($array);
        $array = \array_map('unserialize', $array);
        return $array;
    }
    /**
     * An improved array_search that allows for partial matching of strings values in associative arrays.
     *
     * @param   string   $needle         The text to search for within the array.
     * @param   array    $haystack       Associative array to search in to find $needle.
     * @param   boolean  $caseSensitive  True to search case sensitive, false otherwise.
     *
     * @return  mixed    Returns the matching array $key if found, otherwise false.
     *
     * @since   1.0
     */
    public static function arraySearch($needle, array $haystack, $caseSensitive = \true)
    {
        foreach ($haystack as $key => $value) {
            $searchFunc = $caseSensitive ? 'strpos' : 'stripos';
            if ($searchFunc($value, $needle) === 0) {
                return $key;
            }
        }
        return \false;
    }
    /**
     * Method to recursively convert data to a one dimension array.
     *
     * @param   array|object  $array      The array or object to convert.
     * @param   string        $separator  The key separator.
     * @param   string        $prefix     Last level key prefix.
     *
     * @return  array
     *
     * @since   1.3.0
     */
    public static function flatten($array, $separator = '.', $prefix = '')
    {
        if ($array instanceof \Traversable) {
            $array = \iterator_to_array($array);
        } elseif (\is_object($array)) {
            $array = \get_object_vars($array);
        }
        $result = [];
        foreach ($array as $k => $v) {
            $key = $prefix ? $prefix . $separator . $k : $k;
            if (\is_object($v) || \is_array($v)) {
                $result[] = static::flatten($v, $separator, $key);
            } else {
                $result[] = [$key => $v];
            }
        }
        return \array_merge(...$result);
    }
    /**
     * Merge array recursively.
     *
     * @param   array  ...$args  Array list to be merged.
     *
     * @return  array  Merged array.
     *
     * @since   2.0.0
     * @throws  \InvalidArgumentException
     */
    public static function mergeRecursive(...$args) : array
    {
        $result = [];
        foreach ($args as $i => $array) {
            if (!\is_array($array)) {
                throw new \InvalidArgumentException(\sprintf('Argument #%d is not an array.', $i + 2));
            }
            foreach ($array as $key => &$value) {
                if (\is_array($value) && isset($result[$key]) && \is_array($result[$key])) {
                    $result[$key] = static::mergeRecursive($result[$key], $value);
                } else {
                    $result[$key] = $value;
                }
            }
        }
        return $result;
    }
}
