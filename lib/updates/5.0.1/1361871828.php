<?php

$mod = new waModel();
try {
    $mod->exec('SELECT comment FROM shop_coupon LIMIT 0');
} catch (waDbException $e) {
    $mod->exec('ALTER TABLE `shop_coupon` ADD `comment` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `value`');
}

