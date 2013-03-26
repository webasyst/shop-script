<?php

$model = new waModel();
$model->exec("ALTER TABLE `shop_product_reviews` CHANGE contact_id contact_id INT(11) UNSIGNED NOT NULL DEFAULT 0");
$model->exec("ALTER TABLE `shop_coupon` CHANGE create_contact_id create_contact_id INT(11) UNSIGNED NOT NULL DEFAULT 0");
