<?php

class shopFrontApiFeatureFormatter extends shopFrontApiFormatter
{
    public function format($feature = [])
    {
        if (!$feature) {
            return [];
        }

        $allowed_fields = [
            'id' => 'integer',
            'code' => 'string',
            // 'status' => 'string',
            'name' => 'string',
            'type' => 'string',
            'selectable' => 'integer',
            'multiple' => 'integer',
            'count' => 'integer',
            'default_unit' => 'string',
            'values' => 'array'
        ];
        if (isset($feature['value'])) {
            $feature['value'] = $this->getValue($feature['value']);
            $allowed_fields += (is_string($feature['value']) ? ['value' => 'string'] : ['value' => 'object']);
        } elseif (isset($feature['values'])) {
            $f_vals = [];
            foreach ((array) $feature['values'] as $_value) {
                $f_vals[] = $this->getValue($_value);
            }
            $feature['values'] = $f_vals;
        }

        return array_intersect_key(self::formatFieldsToType($feature, $allowed_fields), $allowed_fields);
    }

    public function formatSelectable($selectable = [])
    {
        $allowed_fields = [
            'sku_id' => 'integer',
            'feature_id' => 'integer',
            'price' => 'number',
            'available' => 'boolean',
            'image_id' => 'integer',
        ];

        return array_intersect_key(self::formatFieldsToType($selectable, $allowed_fields), $allowed_fields);
    }

    protected function getValue($value)
    {
        if ($value instanceof shopColorValue) {
            return [
                'name' => (string)$value->value,
                'value' => (string)$value->hex,
            ];
        } elseif (isset($value['name'], $value['value'])) {
            return [
                'name' => (string)$value['name'],
                'value' => (string)$value['value'],
            ];
        }

        return (string) $value;
    }
}
