<?php
class shopFeatureValuesDividerModel extends shopFeatureValuesModel
{
    protected $table = null;

    protected function getSearchCondition()
    {
        return '`value`= i:value';
    }

    protected function parseValue($value, $type)
    {
        return array('value' => '-');
    }

    protected function getValue($row)
    {
        return null;
    }

    /**
     * @param string $field
     * @param int|int[] $value
     * @param int $limit
     * @return array array of values for multiple features or values of single feature
     */
    public function getValues($field, $value = null, $limit = null)
    {
        $values = array();
        switch ($field) {
            case 'id':
                foreach ((array)$value as $id) {
                    $values[$id] = $this->getValue($this->parseValue(null, null));
                }
                break;
            case 'feature_id':
                $raw_values = array(
                    1 => $this->getValue($this->parseValue(null, null)),
                );
                foreach ((array)$value as $id) {
                    $values[$id] = $raw_values;
                }
                break;
            default:
                //incomplete case
                break;
        }
        if (($field === true) || is_array($value) || ($field != 'feature_id')) {
            return $values;
        } else {
            return isset($values[$value]) ? $values[$value] : array();
        }
    }

    public function getId($feature_id, $value, $type = null, $update = true)
    {
        $result = array(
            1 => '-',
        );
        return (is_array($value) && !isset($value['value'])) ? $result : reset($result);
    }

    /**
     * @param $product_id
     * @param $feature_id
     * @param string $field
     * @return array array of values for multiple features or values of single feature
     */
    public function getProductValues($product_id, $feature_id, $field = 'value')
    {
        $sql = "SELECT pf.product_id, pf.feature_value_id  ".$field." FROM shop_product_features pf
                WHERE pf.product_id IN (i:0) AND pf.feature_id = i:1";
        return $this->query($sql, $product_id, $feature_id)->fetchAll('product_id', true);
    }

    public function addValue($feature_id, $value, $id = null, $type = null, $sort = null)
    {
        $row = $this->parseValue($value, $type);
        $row['id'] = ($id === null) ? $this->getId($feature_id, $value) : $id;
        $row['sort'] = $id;
        return $row;
    }

    public function countByField($field, $value = null)
    {
        return 1;
    }

    public function deleteByField($field, $value = null)
    {
        return true;
    }

}


/**
 * Class shopBooleanValue
 * @property boolean $value
 * @property string $feature_id
 * @property-read int $sort
 */
class shopDividerValue implements ArrayAccess
{
    private $value = '-';
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
        return ($this->value === null) ? '' : '<hr/>';
    }
}