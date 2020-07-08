<?php
class shopFeatureValuesDateModel extends shopFeatureValuesDoubleModel
{
    protected function parseValue($value, $type)
    {
        return array(
            'value' => !empty($value) ? strtotime($value) : null,
        );
    }

    protected function getValue($row)
    {
        return new shopDateValue($row);
    }
}