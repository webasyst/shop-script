<?php

$model = new waModel();

try {
    $model->query("SELECT `paid_datetime` FROM `shop_order` WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_order` ADD `paid_datetime` DATETIME AFTER `paid_date`");
}
