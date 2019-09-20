<?php

$m = new waModel();

try {
    $m->query("SELECT * FROM `shop_promo_rules` WHERE 0");
} catch (waDbException $e) {
    $_i = new shopInstaller();
    $_i->createTable('shop_promo_rules');
}