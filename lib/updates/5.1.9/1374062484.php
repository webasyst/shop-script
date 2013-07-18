<?php

$m = new waModel();
try {
    $sql = 'SELECT `parent_id` FROM `shop_feature` WHERE 0';
    $m->query($sql);
} catch (waDbException $e) {
    $sql = 'ALTER TABLE  `shop_feature` ADD  `parent_id` INT NULL DEFAULT NULL AFTER  `id`';
    $m->query($sql);
}