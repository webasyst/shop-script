<?php

$model = new waModel();
try {
    $model->query("SELECT `same_state_id` FROM `shop_followup` WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_followup` ADD `same_state_id` tinyint DEFAULT 0 AFTER  `first_order_only`");
}
