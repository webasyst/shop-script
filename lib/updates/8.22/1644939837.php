<?php

$model = new waModel();

foreach (array('before_count', 'after_count', 'diff_count') as $field) {
    $sql = "ALTER TABLE `shop_product_stocks_log` MODIFY COLUMN `{$field}` DECIMAL(15,3) NULL DEFAULT NULL";
    $model->exec($sql);
}
