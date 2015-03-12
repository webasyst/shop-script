<?php
$model = new waModel();

try {
    $model->exec("ALTER TABLE `shop_order_params` ADD INDEX `name` (`name`)");
} catch (waDbException $e) {
}

try {
    $model->exec("SELECT source FROM shop_customer WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_customer` ADD `source` VARCHAR(255) NULL DEFAULT NULL");

    //
    // Update fetch data for shop_customer.source using existing orders.
    //

    // For each customer get the source from their most early order that actually has a source
    $sql = "SELECT o.contact_id, op.order_id, op.value AS source
            FROM shop_order AS o
                JOIN shop_order_params AS op
                    ON op.order_id=o.id
            WHERE op.name IN ('referer_host', 'utm_campaign')";
    $customer_source = array();
    foreach($model->query($sql) as $row) {
        if (empty($customer_source[$row['contact_id']]) || $customer_source[$row['contact_id']]['order_id'] > $row['order_id']) {
            $customer_source[$row['contact_id']] = $row;
        }
    }

    // When first order of a customer had no source, remove it from $customer_source
    $sql = "SELECT o.contact_id, o.id AS order_id FROM shop_order AS o";
    foreach($model->query($sql) as $row) {
        if (!empty($customer_source[$row['contact_id']]) && $customer_source[$row['contact_id']]['order_id'] > $row['order_id']) {
            unset($customer_source[$row['contact_id']]);
        }
    }

    // Update shop_customer.source from $customer_source
    while ($customer_source) {
        $values = array();
        $part = array_splice($customer_source, 0, min(50, count($customer_source)));
        foreach($part as $row) {
            $values[] = "({$row['contact_id']},{$row['order_id']},'".$model->escape($row['source'])."')";
        }
        $sql = "INSERT INTO shop_customer (contact_id, last_order_id, source)
                VALUES ".join(', ', $values)."
                ON DUPLICATE KEY UPDATE source=VALUES(source)";
        $model->exec($sql);
    }
}

