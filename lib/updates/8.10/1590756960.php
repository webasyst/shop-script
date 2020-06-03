<?php

$model = new waModel();

try {
    $model->query('SELECT icon, logo FROM shop_product_code WHERE 0');
} catch (waDbException $e) {
    $sql = 'ALTER TABLE shop_product_code
                ADD icon VARCHAR(255) NULL DEFAULT NULL,
                ADD logo VARCHAR(255) NULL DEFAULT NULL';
    $model->exec($sql);
}