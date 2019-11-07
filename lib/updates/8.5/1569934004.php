<?php
$m = new waModel();

try {
    $m->query("SELECT `json_params` FROM `shop_set` WHERE 0");
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `shop_set` ADD `json_params` TEXT NULL");
}