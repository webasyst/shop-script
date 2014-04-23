<?php
class shopFeatureValuesDoubleModel extends shopFeatureValuesModel
{
    protected $table = 'shop_feature_values_double';

    protected function getSearchCondition()
    {
        return '`value`= :value';
    }

    protected function parseValue($value, $type)
    {
        return array('value' => $this->castValue('double', $value));
    }

    public function getValueIdsByRange($feature_id, $min, $max)
    {
        $sql = 'SELECT id FROM '.$this->table.'
                WHERE feature_id = i:0';
        if ($min !== null && $min !== '') {
            $sql .= ' AND value >= f:1';
        }
        if ($max !== null && $max !== '') {
            $sql .= ' AND value <= f:2';
        }
        return $this->query($sql, $feature_id, $min, $max)->fetchAll(null, true);
    }
}
