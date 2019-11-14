<?php

$model = new waModel();

try {
    $model->query("SELECT `auth_date` FROM `shop_order` WHERE 0");
} catch (waDbException $e) {
    $model->exec("UPDATE `shop_order` SET `create_datetime` = '1970-01-01 00:00:00' WHERE CAST(`create_datetime` AS CHAR(20)) = '0000-00-00 00:00:00'");

    $model->exec("ALTER TABLE `shop_order` ALTER COLUMN `paid_date` SET DEFAULT NULL");
    $model->exec("UPDATE `shop_order` SET `paid_date` = NULL WHERE CAST(`paid_date` AS CHAR(10)) = '0000-00-00'");
    $model->exec("ALTER TABLE `shop_order` ADD `auth_date` date NULL DEFAULT NULL AFTER `paid_date`");
}
