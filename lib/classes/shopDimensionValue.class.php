<?php
/**
 * Class shopDimensionValue
 * @property-read string $value
 * @property-read string $unit
 * @property-read string $unit_name
 * @property-read string $type
 * @property-read string $value_base_unit
 * @property-read string $base_code
 */
class shopDimensionValue implements ArrayAccess
{
    private $value;
    private $unit;
    private $type;
    private $value_base_unit;
    private $base_code;
    private $format = false;

    public function __construct($row)
    {
        foreach ($row as $field => $value) {
            $this->$field = $value;
        }
    }

    public function __set($field, $value)
    {
        return $this->$field = $value;
    }

    public function __get($field)
    {
        switch ($field) {
            case 'units':
                return shopDimension::getUnits($this->unit);
                break;
            case 'unit_name':
                return _w($this->unit);
                break;
            default:
                return isset($this->$field) ? $this->$field : $this->convert($field);
                break;
        }
    }

    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    public function offsetSet($offset, $value)
    {
        return $this->__set($offset, $value);
    }

    public function offsetUnset($offset)
    {

    }

    public function offsetExists($offset)
    {
        return true;
    }

    public function __toString()
    {
        return ($this->value === null) ? '' : ($this->format ? sprintf($this->format, $this->value, _w($this->unit)) : $this->value.' '._w($this->unit));
    }

    public function format($format)
    {
        if ($this->value === null) {
            return '';
        } else {
            return ($format === false) ? ($this->value.' '.$this->unit_name) : sprintf($format, $this->value, $this->unit_name);
        }
    }

    public function convert($unit, $format = null)
    {
        if ($format === null) {
            $format = $this->format;
        }
        $value = shopDimension::getInstance()->convert($this->value, $this->type, $unit, $this->unit);
        return ($format === false) ? $value : sprintf($format, $value, _w($unit));
    }

    public function is_null()
    {
        return is_null($this->value);
    }
}
