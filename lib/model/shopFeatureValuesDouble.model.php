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
}
