<?php
$model = new waModel();
$tables = array(
    'shop_product'      => 'id',
    'shop_product_skus' => 'product_id',
    'shop_category'     => 'id',
);
foreach ($tables as $table => $field) {
    try {
        $sql = "SELECT `id_1c` FROM `{$table}` WHERE 0";
        $model->query($sql);
    } catch (waDbException $ex) {
        $sql = "ALTER TABLE  `{$table}` ADD  `id_1c` VARCHAR( 36 ) NULL DEFAULT NULL AFTER  `{$field}` ,
ADD INDEX `id_1c` (  `id_1c` )";
        $model->query($sql);
    }
}
