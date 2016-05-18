<?php
$m = new waModel();
try {
    $m->exec("ALTER TABLE `shop_stock` ADD `public` INT(1) NOT NULL DEFAULT '1'");
} catch (Exception $e) {
}
