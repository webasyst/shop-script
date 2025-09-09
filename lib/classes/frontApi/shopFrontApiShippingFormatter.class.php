<?php
/**
 * Formatter for /shipping/list
 */
class shopFrontApiShippingFormatter extends shopFrontApiFormatter
{
    public function format($shipping = [])
    {
        if (!$shipping) {
            return [];
        }

        $allowed_fields = [
            'id' => 'integer',
            'plugin' => 'string',
            'name' => 'string',
            'description' => 'string',
            'logo' => 'string',
        ];

        return array_intersect_key(self::formatFieldsToType($shipping, $allowed_fields), $allowed_fields);
    }
}
