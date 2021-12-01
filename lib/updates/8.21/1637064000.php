<?php
$model = new waModel();
try {
    $model->exec('SELECT `url` FROM `shop_coupon` WHERE 0');
} catch (waDbException $e) {
    $query = 'ALTER TABLE `shop_coupon` ADD `url` TEXT DEFAULT NULL AFTER `value`';
    $model->exec($query);
}