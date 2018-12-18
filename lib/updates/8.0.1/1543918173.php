<?php

$m = new waModel();


try {
    $m->exec('ALTER TABLE `shop_product_features` ADD INDEX `feature_product`(`feature_id`, `feature_value_id`, `product_id`, `sku_id`)');
} catch (waDbException $e) {

}

try {
    $m->exec('ALTER TABLE `shop_category_products` ADD INDEX `product_category`(`product_id`, `category_id`)');
} catch (waDbException $e) {

}
