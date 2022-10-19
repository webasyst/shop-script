<?php

$model = new waModel();

try {
    $model->exec("SELECT `sort_products` FROM `shop_set` WHERE 0");
} catch(Exception $e) {
    $query = 'ALTER TABLE `shop_set` ADD `sort_products` VARCHAR(32) NULL DEFAULT NULL AFTER `sort`';
    $model->exec($query);
}
