<?php

/**
 * Part of the Joomla Framework Registry Package
 *
 * @copyright  Copyright (C) 2013 Open Source Matters, Inc.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */
namespace _JchOptimizeVendor\Joomla\Registry\Format;

use _JchOptimizeVendor\Joomla\Registry\FormatInterface;
use _JchOptimizeVendor\Symfony\Component\Yaml\Dumper as SymfonyYamlDumper;
use _JchOptimizeVendor\Symfony\Component\Yaml\Parser as SymfonyYamlParser;
use _JchOptimizeVendor\Symfony\Component\Yaml\Yaml as SymfonyYaml;
use function sprintf;
/**
 * YAML format handler for Registry.
 *
 * @since  1.0.0
 */
class Yaml implements FormatInterface
{
    /**
     * The YAML parser class.
     *
     * @var    SymfonyYamlParser
     * @since  1.0.0
     */
    private $parser;
    /**
     * The YAML dumper class.
     *
     * @var    SymfonyYamlDumper
     * @since  1.0.0
     */
    private $dumper;
    /**
     * Construct to set up the parser and dumper
     *
     * @since   1.0.0
     */
    public function __construct()
    {
        if (!\class_exists(SymfonyYaml::class)) {
            throw new \RuntimeException(\sprintf('The "%s" class could not be found, make sure you have installed the "symfony/yaml" package.', SymfonyYaml::class));
        }
        $this->parser = new SymfonyYamlParser();
        $this->dumper = new SymfonyYamlDumper();
    }
    /**
     * Converts an object into a YAML formatted string.
     * We use json_* to convert the passed object to an array.
     *
     * @param  object|array  $object   Data source object.
     * @param  array   $options  Options used by the formatter.
     *
     * @return  string  YAML formatted string.
     *
     * @since   1.0.0
     */
    public function objectToString($object, array $options = [])
    {
        $array = \json_decode(\json_encode($object), \true);
        return $this->dumper->dump($array, 2, 0);
    }
    /**
     * Parse a YAML formatted string and convert it into an object.
     * We use the json_* methods to convert the parsed YAML array to an object.
     *
     * @param  string  $data     YAML formatted string to convert.
     * @param  array   $options  Options used by the formatter.
     *
     * @return  object  Data object.
     *
     * @since   1.0.0
     */
    public function stringToObject($data, array $options = [])
    {
        $array = $this->parser->parse(\trim($data));
        return (object) \json_decode(\json_encode($array));
    }
}
