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
        return $this->$field = $value;
    }

    public function __get($field)
    {
        if ($field == 'value' && !empty($this->timestamp)) {
            return self::timestampToDate($this->timestamp);
        }
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
