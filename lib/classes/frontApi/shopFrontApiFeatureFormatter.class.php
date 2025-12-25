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
            'values' => 'array',
            'subfeatures' => [
                'id' => 'integer',
                'code' => 'string',
                'type' => 'string',
            ],
        ];
        if (isset($feature['value'])) {
            $feature['value'] = $this->getValue($feature['value'], null);
            $allowed_fields += (is_string($feature['value']) ? ['value' => 'string'] : ['value' => 'object']);
        } elseif (isset($feature['values'])) {
            $f_vals = [];
            foreach ((array) $feature['values'] as $value_id => $value) {
                $f_vals[] = $this->getValue($value, isset($feature['id']) ? (int)$value_id : null);
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

    protected function getValue($value, ?int $value_id)
    {
        if ($value instanceof shopCompositeValue) {
            $result = [
                'id' => null,
                'name' => null,
                'value' => (string)$value,
                'parts' => [],
            ];
            for ($i = 0; !empty($value[$i]) && $i < 4; $i++) {
                if ($value[$i] instanceof shopDimensionValue && !$value[$i]['id']) {
                    break;
                }
                $result['parts'][] = $this->getValue($value[$i], ifset($value, $i, 'id', null));
            }
            return $result;
        } elseif ($value instanceof shopColorValue) {
            return [
                'id' => $value_id,
                'name' => (string)$value->value,
                'value' => (string)$value->hex,
            ];
        } elseif (isset($value['name'], $value['value'])) {
            return [
                'id' => $value_id,
                'name' => (string)$value['name'],
                'value' => (string)$value['value'],
            ];
        }
        return [
            'id' => (int)$value_id,
            'name' => null,
            'value' => (string)$value,
        ];
    }
}
