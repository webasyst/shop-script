<?php

$model = new waModel();
try {
    $model->query("SELECT `sort` FROM `shop_product_features_selectable` where 0");
} catch (waDbException $e) {
    $sql = "ALTER TABLE `shop_product_features_selectable` ADD `sort` INT NOT NULL DEFAULT '0'";
    $model->exec($sql);
}
