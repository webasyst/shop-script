<?php

$model = new waModel();

try {
    $model->exec("SELECT balance FROM `shop_affiliate_transaction` WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_affiliate_transaction` CHANGE `balance_after` `balance` decimal(15,4) NOT NULL");
}
