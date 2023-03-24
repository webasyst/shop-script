<?php

class shopCompositeValue implements ArrayAccess
{
    /**
     * @var array
     */
    private $values;

    public function __construct($code, &$features = array())
    {
        $flag = true;
        for ($i = 0; $i < 3; $i++) {
            $key = $code.'.'.$i;
            if (array_key_exists($key, $features)) {
                $flag = false;
                $this->values[$i] = $features[$key];
                unset($features[$key]);
            } elseif ($flag) {
                $this->values[$i] = '';
            }
        }
    }

    public function __set($field, $value)
    {
        return null;
    }

    public function __get($field)
    {
        if ($field === 'html') {
            return $this->__toString();
        }
        if ($field === 'compare') {
            $value = '';
            foreach ($this->values as $v) {
                $value .= ':'.trim(isset($v['compare']) ? $v['compare'] : $v);
            }
            return $value;
        }
        return isset($this->values[$field]) ? $this->values[$field] : new shopDimensionValue(array());
    }

    /**
     * https://www.php.net/manual/ru/migration81.incompatible.php#migration81.incompatible.core.type-compatibility-internal
     *
     * @param $offset
     * @return mixed|shopDimensionValue|string
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    /**
     * @param $offset
     * @param $value
     * @return void|null
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
        return true;
    }

    public function __toString()
    {
        return $this->toString();
    }

    /**
     * @return string
     */
    public function format()
    {
        return $this->toString(false);
    }

    /**
     * @param bool $with_unit
     * @return string
     */
    private function toString($with_unit = true)
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
                if (is_object($value) && method_exists($value, 'convert')) {
                    $values[] = $value->convert($v->unit, false);
                } else {
                    $values[] = 'null';
                }
            }

            return implode(' × ', $values).($with_unit ? ' '.$v->unit_name : '');
        }
        return implode(' × ', $this->values);
    }

    public static function parse($feature, $value, $as_string = false)
    {
        $value_pattern = '(\d+|[\.,]\d+|\d+[\.,]\d+)';
        $multi_pattern = '[XxХх×✕✖\*\s]+';
        $unit_pattern = '(\s+.+)?';

        $parsed_value = false;

        if (!preg_match('/\.[0-3]$/', $feature['code'])
            && preg_match('/^([23])d\\./', $feature['type'], $matches)
        ) {
            $n = $matches[1];
            $pattern = '/^'.implode($multi_pattern, array_fill(0, $n, $value_pattern)).$unit_pattern.'$/ui';
            if (preg_match($pattern, trim($value), $matches)) {
                $unit = trim(ifset($matches[$n + 1]));
                $parsed_value = array();
                for ($i = 0; $i < $n; $i++) {
                    $c_code = $feature['code'].'.'.$i;
                    $_value = str_replace(',', '.', $matches[$i + 1]);
                    if ($as_string) {
                        $parsed_value[$c_code] = trim($_value.' '.$unit);
                    } else {
                        $parsed_value[$c_code] = array(
                            'value' => $_value,
                            'unit'  => $unit,
                        );
                    }
                }
            } else {
                //waLog::log(sprintf('Error during parse %dD feature value [%s]', $n, $value), 'shop/features.error.log');
            }
        }
        return $parsed_value;
    }
}
