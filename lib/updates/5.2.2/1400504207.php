<?php

$model = new waModel();
try {
    $model->query("SELECT type FROM shop_affiliate_transaction WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE shop_affiliate_transaction ADD type VARCHAR (32) NULL DEFAULT NULL");
    // order_bonus
    $model->exec("UPDATE shop_affiliate_transaction SET type='order_bonus'
                  WHERE type IS NULL AND order_id IS NOT NULL AND amount > 0");
    // deposit
    $model->exec("UPDATE shop_affiliate_transaction SET type='deposit'
                  WHERE type IS NULL AND order_id IS NULL AND amount > 0");
    // order_cancel
    $model->exec("UPDATE shop_affiliate_transaction SET type='order_cancel'
                  WHERE type IS NULL AND order_id IS NOT NULL AND (comment IS NULL OR comment = '') AND amount < 0");
    // order_discount
    $model->exec("UPDATE shop_affiliate_transaction SET type='order_discount'
                  WHERE type IS NULL AND order_id IS NOT NULL AND amount < 0");
    // withdrawal
    $model->exec("UPDATE shop_affiliate_transaction SET type='withdrawal'
                  WHERE type IS NULL AND order_id IS NULL AND amount < 0");
}