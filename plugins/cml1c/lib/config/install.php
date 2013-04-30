<?php
$model = new waModel();
try {
    $sql = 'SELECT `id_1c` FROM `shop_product` WHERE 0';
    $model->query($sql);
} catch (waDbException $ex) {
    $sql = 'ALTER TABLE  `shop_product` ADD  `id_1c` VARCHAR( 36 ) NULL DEFAULT NULL AFTER  `id` ,
ADD INDEX (  `id_1c` )';
    $model->query($sql);
}

try {
    $sql = 'SELECT `id_1c` FROM `shop_product_skus` WHERE 0';
    $model->query($sql);
} catch (waDbException $ex) {
    $sql = 'ALTER TABLE  `shop_product_skus` ADD  `id_1c` VARCHAR( 36 ) NULL DEFAULT NULL AFTER  `product_id` ,
ADD INDEX (  `id_1c` )';
    $model->query($sql);
}
try {
    $sql = 'SELECT `id_1c` FROM `shop_category` WHERE 0';
    $model->query($sql);
} catch (waDbException $ex) {
    $sql = 'ALTER TABLE  `shop_category` ADD  `id_1c` VARCHAR( 36 ) NULL DEFAULT NULL AFTER  `id` ,
ADD INDEX (  `id_1c` )';
    $model->query($sql);
}
