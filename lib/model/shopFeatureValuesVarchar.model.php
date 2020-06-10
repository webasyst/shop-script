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
        $value = mb_substr(trim($value), 0, 255);
        return array(
            'value'        => $value,
            'search_value' => preg_replace('@([%_\\\\])@', '\\\\$1', $value),
        );
    }

    public function getValues($field, $value = null, $limit = null)
    {
        $result = parent::getValues($field, $value, $limit);
        if (($field === true) || is_array($value) || ($field != 'feature_id')) {
            $filter = &$result;
        } else {
            $filter = [&$result];
        }

        foreach($filter as $feature_id => $values) {
            foreach($values as $value_id => $val) {
                if ($val === '') {
                    $filter[$feature_id][$value_id] = null;
                }
            }
        }

        return $result;
    }
}
