<?php

$mod = new waModel();
$mod->exec("ALTER TABLE `shop_customer` CHANGE `affiliate_bonus` `affiliate_bonus` DECIMAL(15, 4) NOT NULL DEFAULT  '0'");

