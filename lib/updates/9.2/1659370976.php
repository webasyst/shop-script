<?php

$model = new waModel();
$update_null_sql = "UPDATE`shop_type` SET `stock_base_ratio` = '1.0' WHERE `stock_base_ratio` IS NULL";
$model->exec($update_null_sql);
$sql = "ALTER TABLE `shop_type` MODIFY COLUMN `stock_base_ratio` DECIMAL(16,8) UNSIGNED NOT NULL DEFAULT '1.00000000'";
$model->exec($sql);