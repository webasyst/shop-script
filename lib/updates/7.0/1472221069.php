<?php

$m = new waModel();

// delete and refunded orders has be at least once reduced
$m->exec("
    INSERT IGNORE INTO `shop_order_params` (`order_id`, `name`, `value`)
    SELECT id, 'reduce_times', '1' 
    FROM `shop_order` 
    WHERE (state_id = 'deleted' OR state_id = 'refunded')
");

// orders with 'reduced' flag has 'reduce_times' count in at least 1
$m->exec("
    INSERT IGNORE INTO `shop_order_params` (`order_id`, `name`, `value`)
    SELECT order_id, 'reduce_times', '1' 
    FROM `shop_order_params`
    WHERE `name` = 'reduced'
");

