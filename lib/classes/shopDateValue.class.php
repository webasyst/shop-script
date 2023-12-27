<?php


/**
 * Class shopDateValue
 * @property string $value
 * @property string $feature_id
 * @property int $timestamp
 * @property int $sort
 */
class shopDateValue implements ArrayAccess
{
    protected $timestamp;
    protected $feature_id;
    protected $sort;

    public function __construct($row)
    {
        if (empty($row['timestamp']) && !empty($row['value'])) {
            $row['timestamp'] = $row['value'];
        }
        unset($row['value']);
        foreach ($row as $field => $value) {
            $this->$field = $value;
        }
    }

    public function __set($field, $value)
    {
        if ($field == 'value') {
            $field = 'timestamp';
            $value = strtotime($value);
        }
        if (property_exists($this, $field)) {
            $this->$field = $value;
        }
        return $value;
    }

    public function __get($field)
    {
        if ($field == 'value' && !empty($this->timestamp)) {
            return self::timestampToDate($this->timestamp);
        }
        return isset($this->$field) ? $this->$field : null;
    }

    /**
     * https://www.php.net/manual/ru/migration81.incompatible.php#migration81.incompatible.core.type-compatibility-internal
     *
     * @param $offset
     * @return mixed|string|null
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    /**
     * @param $offset
     * @param $value
     * @return false|int|mixed|void
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
        return in_array($offset, array('value', 'feature_id', 'sort', 'timestamp'));
    }

    public function __toString()
    {
        return $this->format(false);
    }

    public static function timestampToDate($timestamp)
    {
        if ($timestamp && is_numeric($timestamp)) {
            return (string) date('Y-m-d', $timestamp);
        }
        return '';
    }

    public static function dateToTimestamp($date)
    {
        if (DateTime::createFromFormat("Y-m-d", $date)) {
            return @strtotime($date);
        } else {
            return '';
        }
    }

    public function is_null()
    {
        return empty($this->timestamp);
    }

    public function format($format)
    {
        if ($this->is_null()) {
            return '';
        }
        $value = (string)waDateTime::format('date', $this->timestamp, waDateTime::getDefaultTimeZone());
        if ($format === false) {
            return $value;
        }
        return sprintf($format, $value, '');
    }
}
