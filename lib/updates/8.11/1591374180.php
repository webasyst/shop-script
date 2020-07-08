<?php

$model = new waModel();

try {
    $model->query('SELECT builtin FROM shop_feature WHERE 0');
} catch (waDbException $e) {
    $sql = 'ALTER TABLE shop_feature ADD builtin INT NULL DEFAULT 0';
    $model->exec($sql);
    $sql = "UPDATE shop_feature SET builtin = 1 WHERE `code` = 'weight' OR `code` = 'gtin'";
    $model->exec($sql);
}