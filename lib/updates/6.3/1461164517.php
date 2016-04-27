<?php

$m = new waModel();

try {
    $m->exec("SELECT `enabled` FROM `shop_promo` WHERE 0");
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `shop_promo` ADD COLUMN `enabled` TINYINT(1) NOT NULL DEFAULT '1'");
}

try {
    $m->exec("SELECT `background_color` FROM `shop_promo` WHERE 0");
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `shop_promo` ADD COLUMN `background_color` VARCHAR(8) NULL AFTER `color`");
}

try {
    $m->exec("SELECT `countdown_datetime` FROM `shop_promo` WHERE 0");
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `shop_promo` ADD COLUMN `countdown_datetime` DATETIME");
}