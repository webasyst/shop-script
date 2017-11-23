<?php
$m = new waModel();
try {
    $m->query("SELECT `options` FROM `shop_plugin` WHERE 0");
} catch (waDbException $e) {
    $sql = 'ALTER TABLE `shop_plugin` ADD `options` TEXT NULL';
    $m->exec($sql);
}
