<?php
$m = new waModel();
try {
    $m->query("SELECT `rights_order_edit` FROM `shop_api_courier` WHERE 0");
} catch (waDbException $e) {
    $sql = "ALTER TABLE `shop_api_courier` ADD `rights_order_edit` INT DEFAULT 0 NOT NULL";
    $m->exec($sql);
}
try {
    $m->query("SELECT `rights_customer_edit` FROM `shop_api_courier` WHERE 0");
} catch (waDbException $e) {
    $sql = "ALTER TABLE `shop_api_courier` ADD `rights_customer_edit` INT DEFAULT 0 NOT NULL";
    $m->exec($sql);
}