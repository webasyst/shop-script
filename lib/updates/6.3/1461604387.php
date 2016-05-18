<?php

$m = new waModel();

try {
    $m->exec("SELECT `video_url` FROM `shop_product` WHERE 0");
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `shop_product` ADD COLUMN `video_url` VARCHAR (255) NULL DEFAULT NULL AFTER `image_filename`");
}