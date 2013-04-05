<?php

$model = new waModel();

try {
    $model->query("SELECT `sku_type` FROM `shop_product` WHERE 0");
} catch (waDbException $e) {
    $model->query("ALTER TABLE `shop_product` ADD sku_type TINYINT(1) NOT NULL DEFAULT 0");
}

try {
    $model->query("SELECT `base_price_selectable` FROM `shop_product` WHERE 0");
} catch (waDbException $e) {
    $model->query("ALTER TABLE `shop_product` ADD base_price_selectable DECIMAL(15, 4) NOT NULL DEFAULT 0");
}