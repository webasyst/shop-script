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
            'status' => 'string',
            'name' => 'string',
            'type' => 'string',
            'selectable' => 'integer',
            'multiple' => 'integer',
            'count' => 'integer',
            'default_unit' => 'string',
            'value_html' => 'string',
        ];
        if (is_array($feature['value'])) {
            $feature['value'] = join(', ', $feature['value']);
        }

        return array_intersect_key(self::formatFieldsToType($feature, $allowed_fields), $allowed_fields);
    }
}
