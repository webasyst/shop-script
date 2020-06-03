<?php

$m = new waModel();
try {
    $m->query("SELECT `default_unit` FROM `shop_feature` WHERE 0");
} catch (waDbException $e) {
    $m->exec('ALTER TABLE `shop_feature` ADD `default_unit` varchar(255) null');
}