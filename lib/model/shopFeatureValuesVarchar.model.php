<?php

class shopFeatureValuesVarcharModel extends shopFeatureValuesModel
{
    protected $table = 'shop_feature_values_varchar';

    protected function getSearchCondition()
    {
        return '`value` LIKE s:search_value';
    }

    protected function parseValue($value, $type)
    {
        $value = substr(trim($value), 0, 255);
        return array(
            'value'        => $value,
            'search_value' => preg_replace('@([%_\\\\])@', '\\\\$1', $value),
        );
    }
}
