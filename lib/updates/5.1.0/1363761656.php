<?php
$model = new waModel();
$exists = false;
try {
    $sql = "SELECT `file_description` FROM `shop_product_skus` WHERE 0";
    $model->exec($sql);
    $exists = true;
    $sql = "ALTER TABLE  `shop_product_skus` CHANGE  `file_description`  `file_description` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL";
    $model->exec($sql);
} catch (waDbException $e) {
    if (class_exists('waLog')) {
        waLog::log(basename(__FILE__).': '.$e->getMessage(), 'shop-update.log');
    }
    if ($exists) {
        throw $e;
    }
}
