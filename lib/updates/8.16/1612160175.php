<?php

$model = new waModel();

try {
    $model->query('select `status` from `shop_product_skus` where 0');
} catch (waDbException $e) {
    $query = 'alter table `shop_product_skus` ADD `status` TINYINT(1) NOT NULL DEFAULT 1 AFTER `available`';
    $model->exec($query);
}