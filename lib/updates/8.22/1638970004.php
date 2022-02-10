<?php

$update = [
    'shop_product' => [
        'count_denominator' => "INT UNSIGNED NOT NULL DEFAULT '1' AFTER `count`",
        'order_multiplicity_factor' => "DECIMAL(9,3) NOT NULL DEFAULT '1.0' AFTER `count_denominator`",
        'stock_unit_id' => "INT NOT NULL DEFAULT '0' AFTER `order_multiplicity_factor`",
        'base_unit_id' => "INT NOT NULL DEFAULT '0' AFTER `stock_unit_id`",
        'stock_base_ratio' => "DECIMAL(16,8) UNSIGNED NOT NULL DEFAULT '1.0' AFTER `base_unit_id`",
        'order_count_min' => "DECIMAL(15,3) UNSIGNED NOT NULL DEFAULT '1.0' AFTER `stock_base_ratio`",
        'order_count_step' => "DECIMAL(15,3) UNSIGNED NOT NULL DEFAULT '1.0' AFTER `order_count_min`",
        'base_price' => "DECIMAL(15,4) UNSIGNED NOT NULL DEFAULT '0' AFTER `order_count_step`",
        'min_base_price' => "DECIMAL(15,4) UNSIGNED NOT NULL DEFAULT '0' AFTER `base_price`",
        'max_base_price' => "DECIMAL(15,4) UNSIGNED NOT NULL DEFAULT '0' AFTER `min_base_price`",
    ],
    'shop_order_items' => [
        'quantity_denominator' => "INT UNSIGNED NOT NULL DEFAULT '1' AFTER `quantity`",
        'stock_unit_id' => "INT NOT NULL DEFAULT '0'",
    ],
    'shop_product_skus' => [
        'stock_base_ratio' => "DECIMAL(16,8) UNSIGNED AFTER `available`",
        'order_count_min' => "DECIMAL(15,3) UNSIGNED AFTER `stock_base_ratio`",
        'order_count_step' => "DECIMAL(15,3) UNSIGNED AFTER `order_count_min`",
    ]
];

$model = new waModel();
foreach ($update as $table => $columns) {
    foreach ($columns as $col => $definition) {
        try {
            $model->query("SELECT `{$col}` FROM `{$table}` WHERE 0");
        } catch (waDbException $e) {
            $sql = "ALTER TABLE `{$table}` ADD {$col} {$definition}";
            $model->exec($sql);
        }
    }
}

try {
    $model->query("SELECT * FROM shop_unit WHERE 0");
} catch (waDbException $e) {
    $_installer = new shopInstaller();
    $_installer->createTable('shop_unit');
}

$sql = 'UPDATE `shop_product` SET `base_price` = `price`, `min_base_price` = `min_price`, `max_base_price` = `max_price`
        WHERE `base_price` = 0';
$model->exec($sql);
