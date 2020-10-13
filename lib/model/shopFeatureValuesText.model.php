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
        if (is_array($value)) {
            $value = ifempty($value, 'value', '');
        }
        return array(
            'value'        => trim($value),
            'search_value' => preg_replace('@([%_\\\\])@', '\\\\$1', trim($value)),
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
