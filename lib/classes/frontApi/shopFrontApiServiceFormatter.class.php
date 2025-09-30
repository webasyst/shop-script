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

    public function formatService($service)
    {
        $allowed_fields = [
            'id' => 'integer',
            'name' => 'string',
            'description' => 'string',
//            'price' => 'number',
            'currency' => 'string',
            'variant_id' => 'integer',
//            'tax_id' => 'integer',
            'variants' => 'array',
            'sort' => 'integer',
        ];

        return array_intersect_key(self::formatFieldsToType($service, $allowed_fields), $allowed_fields);
    }

    public function skuService($_sku_service)
    {
        $allowed_fields = [
            'service_id' => 'integer',
            'variant_id' => 'integer',
            'name' => 'string',
            'price' => 'number',
        ];

        $result = array_intersect_key(self::formatFieldsToType($_sku_service, $allowed_fields), $allowed_fields);
        if (!empty($_sku_service['currency'])) {
            $result = self::formatPriceField($result, ['price'], $_sku_service['currency']);
        }
        return $result;
    }
}
