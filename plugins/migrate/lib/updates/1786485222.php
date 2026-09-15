<?php

$wb_tables = array(
    'shop_migrate_wb_snapshots',
    'shop_migrate_wb_parents',
    'shop_migrate_wb_subjects',
    'shop_migrate_wb_cards',
    'shop_migrate_wb_sizes',
    'shop_migrate_wb_attributes',
    'shop_migrate_wb_attribute_values',
    'shop_migrate_wb_prices',
    'shop_migrate_wb_warehouses',
    'shop_migrate_wb_stocks',
    'shop_migrate_wb_type_map',
    'shop_migrate_wb_category_map',
    'shop_migrate_wb_feature_map',
    'shop_migrate_wb_stock_map',
    'shop_migrate_wb_product_map',
    'shop_migrate_wb_sku_map',
    'shop_migrate_wb_image_map',
    'shop_migrate_wb_runs',
);

$db_path = wa()->getAppPath('plugins/migrate/lib/config/db.php', 'shop');

$db_config = include $db_path;

$db = array_intersect_key(
    $db_config,
    array_fill_keys($wb_tables, true)
);

if ($db) {
    $model = new waModel();
    $model->createSchema($db);
}
