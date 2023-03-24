<?php

$model = new waModel();

try {
    $model->query("SELECT `courier_contact_id` FROM `shop_order` WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_order` ADD `courier_contact_id` INT(11)");
}
