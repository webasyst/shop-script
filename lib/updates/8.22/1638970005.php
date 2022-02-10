<?php

$update = [
    'stock_unit_fixed' => "INT NOT NULL DEFAULT '2'",
    'stock_unit_id' => "INT NOT NULL DEFAULT '0'",
    'base_unit_fixed' => "INT NOT NULL DEFAULT '2'",
    'base_unit_id' => "INT NULL",
    'stock_base_ratio_fixed' => "INT NOT NULL DEFAULT '2'",
    'stock_base_ratio' => "DECIMAL(16,8) UNSIGNED NULL",
    'count_denominator_fixed' => "INT NOT NULL DEFAULT '2'",
    'count_denominator' => "INT UNSIGNED NULL",
    'order_multiplicity_factor_fixed' => "INT NOT NULL DEFAULT '2'",
    'order_multiplicity_factor' => "DECIMAL(9,3) NULL DEFAULT NULL",
    'order_count_min_fixed' => "INT NOT NULL DEFAULT '2'",
    'order_count_min' => "DECIMAL(15,3) UNSIGNED NULL",
    'order_count_step_fixed' => "INT NOT NULL DEFAULT '2'",
    'order_count_step' => "DECIMAL(15,3) UNSIGNED NULL",
];

$model = new waModel();
foreach ($update as $col => $definition) {
    try {
        $model->query("SELECT `{$col}` FROM `shop_type` WHERE 0");
    } catch (waDbException $e) {
        $sql = "ALTER TABLE `shop_type` ADD {$col} {$definition}";
        $model->exec($sql);
    }
}