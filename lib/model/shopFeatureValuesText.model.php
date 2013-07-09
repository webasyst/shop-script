<?php
class shopFeatureValuesTextModel extends shopFeatureValuesModel
{
    protected $table = 'shop_feature_values_text';

    protected function getSearchCondition()
    {
        return '(`value` LIKE s:search_value)';
    }

    protected function parseValue($value, $type)
    {
        return array(
            'value'        => trim($value),
            'search_value' => preg_replace('@([%_\\\\])@', '\\\\$1', trim($value)),
        );
    }
}
