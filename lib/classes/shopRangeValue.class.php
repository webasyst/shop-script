<?php

/**
 * Class shopRangeValue
 * @property-read string $begin_base_unit
 * @property-read string $end_base_unit
 */
class shopRangeValue implements ArrayAccess
{
    static private $format = '%s %s';
    /**
     * @var shopDimensionValue
     */
    private $begin;
    /**
     * @var shopDimensionValue
     */
    private $end;
    private $unit;
    private $type;

    /**
     * @param array $row
     */
    public function __construct($row)
    {
        $begin = $end = $row;
        $begin['value'] = $begin['begin'];
        $end['value'] = $end['end'];
        if ($row['type'] == 'date') {
            $this->begin = new shopDateValue($begin);
            $this->end = new shopDateValue($end);
        } else {
            $this->begin = new shopDimensionValue($begin);
            $this->end = new shopDimensionValue($end);
        }
        foreach ($row as $field => $value) {
            if (!isset($this->$field)) {
                $this->$field = $value;
            }
        }
    }

    /**
     * @param string $format
     */
    public static function setFormat($format)
    {
        self::$format = $format;
    }

    public function __toString()
    {
        $str = array();
        if ($this->begin !== null) {
            $str[] = $this->begin->format('%s');
        } else {
            $str[] = '∅';
        }

        if ($this->end !== null) {
            $str[] = $this->end->format('%s');
        } else {
            $str[] = '∞';
        }
        $str = implode(' — ', $str);
        $unit = ($this->begin ? $this->begin->unit_name : ($this->end ? $this->end->unit_name : null));
        if (strlen($unit)) {
            $str .= ' '.$unit;
        }
        return $str;
    }

    /**
     * @param mixed $offset
     * @return boolean true on success or false on failure.
     */
    public function offsetExists($offset)
    {
        return in_array($offset, array('value', 'units', 'compare')) || isset($this->{$offset});
    }


    public function offsetGet($offset)
    {
        return $this->__get($offset);

    }


    public function offsetSet($offset, $value)
    {

    }


    public function offsetUnset($offset)
    {

    }


    public function __set($field, $value)
    {
        return $this->$field = $value;
    }

    public function __get($field)
    {
        if ($field == 'units') {
            return shopDimension::getUnits($this->type);
        }
        if ($field == 'compare') {
            return trim($this->begin_base_unit.':'.$this->end_base_unit);
        }
        if ($field == 'value') {
            $str = array();
            if ($this->begin !== null) {
                $str[] = sprintf(self::$format, _w('from'), $this->begin);
                if ($this->end !== null) {
                    $str[] = '—';
                }
            }

            if ($this->end !== null) {
                $str[] = sprintf(self::$format, _w('to'), $this->end);
            }
            return implode(' ', $str);
        }
        return isset($this->{$field}) ? $this->$field : null;
    }
}
