<?php


/**
 * Class shopBooleanValue
 * @property boolean $value
 * @property string $feature_id
 * @property-read int $sort
 */
class shopBooleanValue implements ArrayAccess
{
    private $value;
    private $feature_id;
    private $sort;

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
        return isset($this->$field) ? $this->$field : null;
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
        return in_array($offset, array('value', 'feature_id', 'sort'));
    }

    public function __toString()
    {
        return ($this->value === null) ? _w('Not defined') : ($this->value ? _w('Yes') : _w('No'));
    }
}
