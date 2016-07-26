<?php

$m = new waModel();

try {
    $m->query("SELECT `type` FROM `shop_push_client` WHERE 0");
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `shop_push_client` ADD COLUMN `type` VARCHAR (255) NOT NULL DEFAULT ''");
}