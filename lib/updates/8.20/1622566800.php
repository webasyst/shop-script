<?php
/** Update 01.06.2021, 20:00:00 */

$model = new waModel();

try {
    $model->query('SELECT `url` FROM `shop_coupon` WHERE 0');
} catch (waDbException $e) {
    $query = 'ALTER TABLE `shop_coupon` ADD `url` TEXT DEFAULT NULL AFTER `value`';
    $model->exec($query);
}