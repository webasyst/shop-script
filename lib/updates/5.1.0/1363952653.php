<?php

$model = new waModel();
$model->exec("UPDATE `shop_product_reviews` SET auth_provider = 'user' WHERE contact_id != 0");

