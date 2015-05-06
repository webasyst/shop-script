<?php
class shopFeatureValuesBooleanModel extends shopFeatureValuesModel
{
    const VALUE_YES = 1;
    const VALUE_NO = 0;
    protected $table = null;

    protected function getSearchCondition()
    {
        return '`value`= i:value';
    }

    protected function parseValue($value, $type)
    {
        static $map;
        if (empty($map)) {
            $map = array(
                array(
                    'values' => array('', null, mb_strtolower(_w('Not defined')), 'not defined'),
                    'value'  => null,
                ),
                array(
                    'values' => array('0', false, 0, 'false', mb_strtolower(_w('No')), 'no'),
                    'value'  => self::VALUE_NO,
                ),
                array(
                    'values' => array('1', true, 1, 'true', mb_strtolower(_w('Yes')), 'yes'),
                    'value'  => self::VALUE_YES,
                ),
            );
        }


        if (is_string($value)) {
            $value = trim(mb_strtolower($value));
        } else if (is_object($value) && $value instanceof shopBooleanValue) {
            return array(
                'value' => $value['value'],
            );
        }
        $matched = false;
        foreach ($map as $value_map) {
            if (in_array($value, $value_map['values'], true)) {
                $value = $value_map['value'];
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            $value = empty($value) ? self::VALUE_NO : self::VALUE_YES;
        }
        return array('value' => $value);
    }

    protected function getValue($row)
    {
        if (!isset($row['sort'])) {
            $row['sort'] = intval($row['value']);
        }
        return new shopBooleanValue($row);
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
                    $values[$id] = $this->getValue($this->parseValue($id, null));
                }
                break;
            case 'feature_id':
                $raw_values = array(
                    0 => $this->getValue($this->parseValue(false, null)),
                    1 => $this->getValue($this->parseValue(true, null)),
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
        $result = array();
        $multi = false;
        if (is_array($value)) {
            if (isset($value['value'])) {
                $values = array($value);
            } else {
                $multi = true;
                $values = $value;
            }
        } else {
            $values = array($value);
        }
        foreach ($values as $value) {
            $data = $this->parseValue($value, $type);
            if ($data['value'] !== null) {
                $result[$data['value']] = $data['value'];
            }
        }
        return $multi ? $result : reset($result);
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
        return 2;
    }

    public function deleteByField($field, $value = null)
    {
        return true;
    }
}
