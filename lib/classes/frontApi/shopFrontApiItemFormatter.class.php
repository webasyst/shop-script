<?php

class shopFrontApiItemFormatter extends shopFrontApiFormatter
{
    public $fields;
    protected $product_fields = null;

    public function format($item)
    {
        $schema = [
            'id' => 'integer',
            'product_id' => 'integer',
            'sku_id' => 'integer',
            'service_id' => 'integer',
            'service_variant_id' => 'integer',
            'parent_id' => 'integer',
            'price' => 'number',
            'total_discount' => 'number',
            'quantity' => 'number',
            'name' => 'string',
            'create_datetime' => 'string',
            'type' => 'string',
            'product' => 'object',
            'service' => 'object',
        ];
        $item = self::formatFieldsToType($item, $schema);
        return array_intersect_key($item, $schema);
    }
}
