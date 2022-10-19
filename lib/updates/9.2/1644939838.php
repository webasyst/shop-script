<?php

$model = new waModel();
try {
    $model->query("SELECT 0 FROM shop_set_group WHERE 0 HAVING 0 LIMIT 0");
} catch(Exception $e) {
    $model->exec("
        CREATE TABLE `shop_set_group` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `sort` INT NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`)
        ) ENGINE = InnoDB
    ");
}

try {
    $model->query("SELECT group_id FROM shop_set WHERE 0");
} catch(Exception $e) {
    $model->exec("
        ALTER TABLE `shop_set` ADD COLUMN `group_id` INT NULL DEFAULT NULL AFTER `id`
    ");
}
