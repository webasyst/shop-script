<?php

$model = new waModel();

try {
    $model->query("SELECT `fulfillment_contact_id` FROM `shop_order` WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_order` ADD `fulfillment_contact_id` INT(11) AFTER `courier_contact_id`");
}

try {
    $model->query("SELECT `cashier_contact_id` FROM `shop_order` WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_order` ADD `cashier_contact_id` INT(11) AFTER `fulfillment_contact_id`");
}

try {
    $model->query("SELECT `manager_contact_id` FROM `shop_order` WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_order` ADD `manager_contact_id` INT(11) AFTER `cashier_contact_id`");
}

try {
    $model->exec("ALTER TABLE `shop_order`
                    ADD INDEX `fulfillment_contact_id` (`fulfillment_contact_id`),
                    ADD INDEX `cashier_contact_id` (`cashier_contact_id`),
                    ADD INDEX `manager_contact_id` (`manager_contact_id`),
                    ADD INDEX `courier_contact_id` (`courier_contact_id`),
                    ADD INDEX `assigned_contact_id` (`assigned_contact_id`)
                ");
} catch (waDbException $e) {
}
