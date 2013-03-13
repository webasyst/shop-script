<?php

$sqls = array();
$sqls['shop_product_skus.compare_price'] = "ALTER TABLE  `shop_product_skus` ADD  `compare_price` decimal(15,4) NOT NULL DEFAULT '0.0000' AFTER  `purchase_price`";

$model = new waModel();
foreach ($sqls as $field => $sql) {
    try {
        list($table, $field) = explode('.', $field, 2);
        $sql_check = 'SELECT `%s` FROM `%s` LIMIT 1';
        $model->query(sprintf($sql_check, $field, $table));
    } catch (waDbException $ex) {
        try {
            $model->query($sql);
        } catch (waDbException $e) {
            if (class_exists('waLog')) {
                waLog::log(basename(__FILE__).': '.$e->getMessage(), 'shop-update.log');
            }
            throw $e;
        }
    }
}
