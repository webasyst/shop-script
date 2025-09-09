<?php
/**
 * Formatter for /shipping/costs
 */
class shopFrontApiShippingVariantFormatter extends shopFrontApiFormatter
{
    public function format($s = [])
    {
        $s = self::formatPriceField($s, ['rate'], $s['currency']);
        $s['logo'] = self::urlToAbsolute($s['logo']);

        $allowed_fields = [
            'variant_id' => 'string',
            'plugin' => 'string',
            'plugin_name' => 'string',
            'name' => 'string',
            'description' => 'string',
            'logo' => 'string',

            'rate' => 'number',
            'rate_exact' => 'string',
            'rate_str' => 'string',
            'rate_html' => 'string',
            'currency' => 'string',
            'type' => 'string',
            'est_delivery' => 'string',
            'delivery_date' => [
                '_multiple' => true,
                '_type' => 'string',
            ],

            // not supported now, but may be in future
            //'possible_addresses' => '' 
        ];

        return array_intersect_key(self::formatFieldsToType($s, $allowed_fields), $allowed_fields);
    }
}
