<?php

$model = new waModel();
if (!$model->fieldExists(array('shop_category', 'status'))) {
    $model->exec("ALTER TABLE `shop_category` ADD `status` TINYINT(1) NOT NULL DEFAULT  '1'");
}

$model->exec("ALTER TABLE `shop_product` CHANGE `status` `status` TINYINT(1) NOT NULL DEFAULT '1'");
if (!$model->query("SELECT COUNT(*) FROM shop_product WHERE status = 1")->fetchField()) {
    $model->exec("UPDATE shop_product SET status = 1");
}
