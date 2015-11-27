<?php

$model = new waModel();
try {
    $model->query('SELECT total_discount FROM shop_order_items WHERE 0');
} catch (waDbException $e) {
    $model->exec("ALTER TABLE shop_order_items ADD total_discount decimal(15,4) NOT NULL DEFAULT 0");
}