<?php
$model = new waModel();
try {
    $model->query("SELECT rounding FROM shop_currency WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_currency` 
                      ADD `rounding` DECIMAL(8,2) NULL DEFAULT NULL AFTER `rate`,
                      ADD `round_up_only` INT(11) NOT NULL DEFAULT '1' AFTER `rounding`");
}
