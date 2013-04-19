<?php
class shopDimensionValue implements ArrayAccess
{
    private $value;
    private $unit;
    private $value_base_unit;
    private $base_code;
    private $format = '%0.2f %s';

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
        if ($field == 'units') {
            return shopDimension::getUnits($this->unit);
        }
        return isset($this->$field) ? $this->$field : $this->convert($field);
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
        return sprintf($this->format, $this->value, _w($this->unit));
    }

    public function format($f)
    {
        return sprintf($f, $this->value, $this->unit);
    }

    public function convert($unit)
    {
        $value = shopDimension::getInstance()->convert($this->value, $unit);
        return sprintf($this->format, $value, $unit);
    }
}
