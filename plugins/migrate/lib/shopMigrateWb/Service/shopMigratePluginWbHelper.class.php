<?php

class shopMigratePluginWbHelper
{
    /**
     * This explicit list is also the security allowlist used by the cleanup
     * controller. Do not replace it with a table-prefix lookup.
     *
     * @return array
     */
    public static function getTablesMeta()
    {
        return array(
            'shop_migrate_wb_snapshots' => array(
                'title'       => _wp('Snapshots'),
                'description' => _wp('Wildberries snapshot history and build state.'),
            ),
            'shop_migrate_wb_parents' => array(
                'title'       => _wp('Parent categories'),
                'description' => _wp('Top-level Wildberries categories stored in snapshots.'),
            ),
            'shop_migrate_wb_subjects' => array(
                'title'       => _wp('Subcategories'),
                'description' => _wp('Wildberries subjects and their parent categories.'),
            ),
            'shop_migrate_wb_cards' => array(
                'title'       => _wp('Product cards'),
                'description' => _wp('Product cards received from Wildberries.'),
            ),
            'shop_migrate_wb_sizes' => array(
                'title'       => _wp('Product variants'),
                'description' => _wp('Wildberries product sizes, barcodes, and variant identifiers.'),
            ),
            'shop_migrate_wb_attributes' => array(
                'title'       => _wp('Characteristics'),
                'description' => _wp('Wildberries characteristic definitions for product subjects.'),
            ),
            'shop_migrate_wb_attribute_values' => array(
                'title'       => _wp('Characteristic values'),
                'description' => _wp('Characteristic values assigned to Wildberries products.'),
            ),
            'shop_migrate_wb_prices' => array(
                'title'       => _wp('Prices'),
                'description' => _wp('Wildberries prices for products and variants.'),
            ),
            'shop_migrate_wb_warehouses' => array(
                'title'       => _wp('Warehouses'),
                'description' => _wp('Wildberries warehouses found in snapshots.'),
            ),
            'shop_migrate_wb_stocks' => array(
                'title'       => _wp('Stocks'),
                'description' => _wp('Wildberries stock quantities by warehouse and variant.'),
            ),
            'shop_migrate_wb_type_map' => array(
                'title'       => _wp('Product type mapping'),
                'description' => _wp('Wildberries subject mappings to Shop-Script product types.'),
            ),
            'shop_migrate_wb_category_map' => array(
                'title'       => _wp('Category mapping'),
                'description' => _wp('Wildberries category mappings to Shop-Script categories.'),
            ),
            'shop_migrate_wb_feature_map' => array(
                'title'       => _wp('Feature mapping'),
                'description' => _wp('Wildberries characteristic mappings to Shop-Script features.'),
            ),
            'shop_migrate_wb_stock_map' => array(
                'title'       => _wp('Warehouse mapping'),
                'description' => _wp('Wildberries warehouse mappings to Shop-Script stocks.'),
            ),
            'shop_migrate_wb_product_map' => array(
                'title'       => _wp('Product mapping'),
                'description' => _wp('Wildberries product card mappings to Shop-Script products.'),
            ),
            'shop_migrate_wb_sku_map' => array(
                'title'       => _wp('Variant mapping'),
                'description' => _wp('Wildberries variant mappings to Shop-Script SKUs.'),
            ),
            'shop_migrate_wb_image_map' => array(
                'title'       => _wp('Image mapping'),
                'description' => _wp('Downloaded Wildberries image mappings to Shop-Script images.'),
            ),
            'shop_migrate_wb_unpriced_products' => array(
                'title'       => _wp('Products without prices'),
                'description' => _wp('Products left unpublished without prices, grouped by Wildberries import run.'),
            ),
            'shop_migrate_wb_runs' => array(
                'title'       => _wp('Import runs'),
                'description' => _wp('Wildberries import progress, counters, and completion state.'),
            ),
        );
    }
}
