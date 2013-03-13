<?php

$model = new waModel();

// add columns is_first and comment
try {
    $model->query("SELECT is_first FROM shop_order WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE shop_order ADD is_first TINYINT(1) NOT NULL DEFAULT 0");
}

try {
    $model->query("SELECT comment FROM shop_order WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE shop_order ADD comment TEXT NULL");
}

// set comment from shop_order_log (action = create)
$sql = "UPDATE shop_order o JOIN shop_order_log l ON o.id = l.order_id AND l.action_id = 'create' SET o.comment = l.text";
$model->exec($sql);

// fill correct is_first
$sql = "UPDATE shop_order o
        JOIN (SELECT contact_id, MIN(id) id FROM `shop_order` WHERE paid_date IS NOT NULL GROUP BY contact_id) as f
        ON o.id = f.id
        SET o.is_first = 1";
$model->exec($sql);