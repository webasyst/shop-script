<?php

$model = new waModel();
try {
    $model->exec("SELECT transport FROM `shop_followup` WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_followup` ADD transport ENUM('sms', 'email') NOT NULL DEFAULT 'email'");
}
