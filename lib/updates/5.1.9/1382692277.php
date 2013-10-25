<?php

$model = new waModel();

try {
    $model->exec("ALTER TABLE shop_order_log ADD INDEX `order_id` (`order_id`)");
} catch (waDbException $e) {
}


try {
    $model->exec("ALTER TABLE shop_order_items DROP INDEX product");
} catch (waDbException $e) {
}

try {
    $model->exec("ALTER TABLE shop_order_items ADD INDEX product_order (product_id, order_id)");
} catch (waDbException $e) {
}

try {
    $model->exec("ALTER TABLE shop_order_items ADD INDEX `order_type` (`order_id`, `type`)");
} catch (waDbException $e) {
}


try {
    $model->exec("ALTER TABLE shop_product_features DROP INDEX sku");
} catch (waDbException $e) {
}

try {
    $model->exec("ALTER TABLE shop_product_features ADD INDEX product_feature (product_id, feature_id, feature_value_id)");
} catch (waDbException $e) {
}