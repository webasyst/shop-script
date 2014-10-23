<?php

$model = new waModel();

// SHOP_NOTIFICATION

try {
    $sql = "SELECT source FROM `shop_notification` WHERE 0";
    $model->query($sql);
} catch(Exception $e) {
    $sql = "ALTER TABLE `shop_notification` ADD COLUMN source VARCHAR(64) NULL DEFAULT NULL";
    $model->exec($sql);
}

try {
    $model->query("SELECT status FROM `shop_notification` WHERE 0");
} catch (Exception $e) {    
    $sql = "ALTER TABLE `shop_notification` ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 1";
    $model->exec($sql);
}

// SHOP_FOLLOWUP

try {
    $sql = "SELECT `from` FROM `shop_followup` WHERE 0";
    $model->query($sql);
} catch (Exception $e) {
    $sql = "ALTER TABLE `shop_followup` ADD COLUMN `from` VARCHAR(32) NULL DEFAULT NULL";
    $model->exec($sql);
}

try {
    $sql = "SELECT `source` FROM `shop_followup` WHERE 0";
    $model->query($sql);
} catch (Exception $e) {
    $sql = "ALTER TABLE `shop_followup` ADD COLUMN `source` VARCHAR(64) NULL DEFAULT NULL";
    $model->exec($sql);
}

try {
    $model->query("SELECT status FROM `shop_followup` WHERE 0");
} catch (Exception $e) {
    $sql = "ALTER TABLE `shop_followup` ADD COLUMN `status` TINYINT(1) NOT NULL DEFAULT 1";
    $model->exec($sql);
}
