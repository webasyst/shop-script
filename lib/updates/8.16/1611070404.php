<?php

$model = new waModel();

try {
    $model->query('select `mass_edit` from `shop_customers_filter` where 0');
} catch (waDbException $e) {
    $sql = 'ALTER TABLE `shop_customers_filter` ADD `mass_edit` INT(11) NULL AFTER `contact_id`';
    $model->exec($sql);
}