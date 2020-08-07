<?php

$model = new waModel();

try {
    $model->query('SELECT protected FROM shop_product_code WHERE 0');
} catch (waDbException $e) {
    $sql = 'ALTER TABLE shop_product_code ADD protected TINYINT(1) DEFAULT 0 NULL';
    $model->exec($sql);
    $sql = "UPDATE shop_product_code SET protected = 1 WHERE `code` = 'chestnyznak'";
    $model->exec($sql);
}

try {
    $model->query('SELECT plugin_id FROM shop_product_code WHERE 0');
} catch (waDbException $e) {
    $sql = 'ALTER TABLE shop_product_code ADD plugin_id varchar(255) NULL';
    $model->exec($sql);
    $sql = "UPDATE shop_product_code SET plugin_id = 'chestnyznak' WHERE `code` = 'chestnyznak'";
    $model->exec($sql);
}