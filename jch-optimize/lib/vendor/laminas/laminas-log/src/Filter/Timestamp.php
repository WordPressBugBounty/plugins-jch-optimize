<?php

namespace _JchOptimizeVendor\Laminas\Log\Filter;

use DateTime;
use _JchOptimizeVendor\Laminas\Log\Exception;
use _JchOptimizeVendor\Laminas\Stdlib\ArrayUtils;
use Traversable;
/**
 * Filters log events based on the time when they were triggered.
 *
 * @author Nikola Posa <posa.nikola@gmail.com>
 */
class Timestamp implements FilterInterface
{
    /**
     * DateTime instance or desired value based on $dateFormatChar.
     *
     * @var int|DateTime
     */
    protected $value;
    /**
     * PHP idate()-compliant format character.
     *
     * @var string|null
     */
    protected $dateFormatChar;
    /**
     * @var string
     */
    protected $operator;
    /**
     * @param int|DateTime|array|Traversable $value DateTime instance or desired value based on $dateFormatChar
     * @param string $dateFormatChar PHP idate()-compliant format character
     * @param string $operator Comparison operator
     * @return Timestamp
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($value, $dateFormatChar = null, $operator = '<=')
    {
        if ($value instanceof Traversable) {
            $value = ArrayUtils::iteratorToArray($value);
        }
        if (\is_array($value)) {
            $dateFormatChar = isset($value['dateFormatChar']) ? $value['dateFormatChar'] : null;
            $operator = isset($value['operator']) ? $value['operator'] : null;
            $value = isset($value['value']) ? $value['value'] : null;
        }
        if ($value instanceof DateTime) {
            $this->value = $value;
        } else {
            if (!\is_int($value)) {
                throw new Exception\InvalidArgumentException(\sprintf('Value must be either DateTime instance or integer; received "%s"', \gettype($value)));
            }
            if (!\is_string($dateFormatChar)) {
                throw new Exception\InvalidArgumentException(\sprintf('Date format character must be supplied as string; received "%s"', \gettype($dateFormatChar)));
            }
            $this->value = $value;
            $this->dateFormatChar = $dateFormatChar;
        }
        if ($operator === null) {
            $operator = '<=';
        } elseif (!\in_array($operator, ['<', 'lt', '<=', 'le', '>', 'gt', '>=', 'ge', '==', '=', 'eq', '!=', '<>'])) {
            throw new Exception\InvalidArgumentException("Unsupported comparison operator: '{$operator}'");
        }
        $this->operator = $operator;
    }
    /**
     * Returns TRUE if timestamp is accepted, otherwise FALSE is returned.
     *
     * @param array $event event data
     * @return bool
     */
    public function filter(array $event)
    {
        if (!isset($event['timestamp'])) {
            return \false;
        }
        $datetime = $event['timestamp'];
        if (!($datetime instanceof DateTime || \is_int($datetime) || \is_string($datetime))) {
            return \false;
        }
        $timestamp = $datetime instanceof DateTime ? $datetime->getTimestamp() : (int) $datetime;
        if ($this->value instanceof DateTime) {
            return \version_compare($timestamp, $this->value->getTimestamp(), $this->operator);
        }
        return \version_compare(\idate($this->dateFormatChar, $timestamp), $this->value, $this->operator);
    }
}
