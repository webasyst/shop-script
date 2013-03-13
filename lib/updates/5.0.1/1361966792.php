<?php

$mod = new waModel();
$mod->exec('
    CREATE TABLE IF NOT EXISTS `shop_discount_by_sum` (
      `type` VARCHAR(32) NOT NULL,
      `sum` DECIMAL(15, 4) NOT NULL,
      `discount` DECIMAL(15, 4) NOT NULL
    ) ENGINE = MYISAM
');

