<?php

$model = new waModel();
$tables = array(
    'shop_service'          => 'id',
    'shop_service_variants' => 'service_id',
);
foreach ($tables as $table => $field) {
    try {
        $sql = "SELECT `cml1c_id` FROM `{$table}` WHERE 0";
        $model->query($sql);
    } catch (waDbException $ex) {
        $sql = "ALTER TABLE `{$table}` ADD `cml1c_id` VARCHAR( 36 ) NULL DEFAULT NULL AFTER `{$field}` ,
ADD INDEX `cml1c_id` ( `cml1c_id` )";
        $model->query($sql);
    }
}
