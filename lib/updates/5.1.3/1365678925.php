<?php

$model = new waModel();
try {
    $model->query("SELECT route FROM shop_category WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE shop_category ADD route VARCHAR(255) NULL DEFAULT NULL");
}