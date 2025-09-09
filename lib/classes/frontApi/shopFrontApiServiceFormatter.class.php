<?php

class shopFrontApiServiceFormatter extends shopFrontApiFormatter
{
    public function format($service, $variants=null)
    {
        if (!$service) {
            return null;
        }
        $variants = ifempty($variants, ifset($service, 'variants', null));
        if (!$variants) {
            return null;
        }
        usort($variants, function($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });
        $variants = array_map([$this, 'formatVariant'], $variants);
        
        $allowed_fields = [
            'id' => 'integer',
            'name' => 'string',
            'description' => 'string',
            //'tax_id' => 'integer',
        ];

        $service = self::formatFieldsToType($service, $allowed_fields);
        return array_intersect_key($service, $allowed_fields) + [
            'variants' => $variants,
        ];
    }

    public function formatVariant($variant)
    {
        $variant = self::formatPriceField($variant, ['price'], $variant['currency']);
        $allowed_fields = [
            'id' => 'integer',
            'name' => 'string',
            "currency" => "string",
            'price' => 'number',
            'price_exact' => 'string',
            'price_str' => 'string',
            'price_html' => 'string',
        ];
        return array_intersect_key(self::formatFieldsToType($variant, $allowed_fields), $allowed_fields);
    }
}
