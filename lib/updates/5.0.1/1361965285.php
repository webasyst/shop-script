<?php
$model = new waModel();
$sql = "ALTER TABLE  `shop_product_skus` CHANGE  `file_size`  `file_size` INT( 11 ) NOT NULL DEFAULT  '0'";
try {
    $model->query($sql);
} catch (waDbException $e) {
    if (class_exists('waLog')) {
        waLog::log(basename(__FILE__).': '.$e->getMessage(), 'shop-update.log');
    }
    throw $e;
}
