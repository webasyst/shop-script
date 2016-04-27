<?php

$m = new waModel();

try {
    $m->exec("SELECT `unsettled` FROM `shop_order` WHERE 0");
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `shop_order` ADD COLUMN `unsettled` TINYINT(1) NOT NULL DEFAULT '0' AFTER `is_first`");
}