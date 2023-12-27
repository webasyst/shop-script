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
        if (property_exists($this, $field)) {
            $this->$field = $value;
        }
        return $value;
    }

    public function __get($field)
    {
        return isset($this->$field) ? $this->$field : null;
    }

    /**
     * https://www.php.net/manual/ru/migration81.incompatible.php#migration81.incompatible.core.type-compatibility-internal
     *
     * @param $offset
     * @return mixed|null
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    /**
     * @param $offset
     * @param $value
     * @return void
     */
    #[ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        return $this->__set($offset, $value);
    }

    /**
     * @param $offset
     * @return void
     */
    #[ReturnTypeWillChange]
    public function offsetUnset($offset)
    {

    }

    /**
     * @param $offset
     * @return bool
     */
    #[ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return in_array($offset, array('value', 'feature_id', 'sort'));
    }

    public function __toString()
    {
        return ($this->value === null) ? _w('Not defined') : ($this->value ? _w('Yes') : _w('No'));
    }
}
