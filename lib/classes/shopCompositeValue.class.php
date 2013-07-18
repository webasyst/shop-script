<?php

class shopCompositeValue implements ArrayAccess
{
    /**
     * @var array
     */
    private $values;

    public function __construct($code, &$features = array())
    {
        for ($i = 0; $i < 3; $i++) {
            $key = $code.'.'.$i;
            if (array_key_exists($key, $features)) {
                $this->values[$i] = $features[$key];
                unset($features[$key]);
            }
        }
    }

    public function __set($field, $value)
    {
        return null;
    }

    public function __get($field)
    {
        return array_key_exists($field, $this->values) ? $this->values[$field] : null;
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
        if (($v = end($this->values)) instanceof shopDimensionValue) {
            /**
             * @var shopDimensionValue $v
             */
            $values = array();
            foreach ($this->values as $value) {
                /**
                 * @var shopDimensionValue $value
                 */
                $values[] = $value->convert($v->unit, false);
            }

            return implode(' × ', $values).' '.$v->unit_name;


        }
        return implode(' × ', $this->values);
    }
}
