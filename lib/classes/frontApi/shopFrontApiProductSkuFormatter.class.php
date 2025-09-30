<?php
/*
 * Formatter for fontend API. Takes data from ProductsCollection and prepares into strict API format.
 */
class shopFrontApiProductSkuFormatter extends shopFrontApiFormatter
{
    public function format(array $sku, ?array $product = null)
    {
        // Compare price should be greater than price
        if ($sku['compare_price'] && ($sku['price'] >= $sku['compare_price'])) {
            $sku['compare_price'] = 0.0;
        }
        if ($sku['count'] !== null && $product && !empty($product['order_multiplicity_factor'])) {
            $sku['count'] = shopFrac::formatQuantityWithMultiplicity($sku['count'], $product['order_multiplicity_factor']);
        }

        if ($product && !empty($product['currency'])) {
            $sku = self::formatPriceField($sku, ['price', 'compare_price'], $product['currency']);
            $sku['currency'] = $product['currency'];
        }

        $sku = self::formatFieldsToType($sku, [
            "id" => "integer",
            "sort" => "integer",
            "image_id" => "integer",
            "price" => "number",
            "compare_price" => "number",
            "currency" => "string",
            "count" => "integer",
            "available" => "integer",
            "status" => "integer",
            "stock_base_ratio" => "number",
            "order_count_min" => "number",
            "order_count_step" => "number",
            "file_size" => "integer",
            "stock" => 'object',
        ]);
        if (empty($sku['image_id'])) {
            $sku['image_id'] = null;
        }

        return array_intersect_key($sku, [
            "id" => 1,
            "sku" => 1,
            "sort" => 1,
            "name" => 1,
            "currency" => 1,
            "price" => 1,
            'price_exact' => 1,
            'price_str' => 1,
            'price_html' => 1,
            //"purchase_price" => 1,
            "compare_price" => 1,
            'compare_price_exact' => 1,
            'compare_price_str' => 1,
            'compare_price_html' => 1,

            "available" => 1,
            "status" => 1,
            "stock_base_ratio" => 1,
            "order_count_min" => 1,
            "order_count_step" => 1,
            //"dimension_id" => 1,

            "count" => 1,
            "stock" => 1,

            "file_name" => 1,
            "file_size" => 1,
            "file_description" => 1,

            "image_id" => 1,
            "features" => 1,
            'services' => 1,
            //"ext" => 1,
            //"image_filename" => 1,
            //"image_description" => 1,
        ]);
    }
}
