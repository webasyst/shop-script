<?php

$m = new waModel();

try {
    // sales_channel => storefront:*
    $sql = "INSERT IGNORE INTO shop_order_params (order_id, name, value)
            SELECT op1.order_id, 'sales_channel', CONCAT('storefront:', op1.value)
            FROM shop_order_params AS op1
                LEFT JOIN shop_order_params AS op2
                    ON op1.order_id = op2.order_id
                        AND op2.name = 'sales_channel'
            WHERE op1.name = 'storefront'
                AND op2.order_id IS NULL";
    $m->exec($sql);
} catch (Exception $e) {
    waLog::log('Unable to set "sales_channel" => "storefront:" params for old orders');
    waLog::log($e->getMessage());
}

try {
    // sales_channel => backend:
    $sql = "INSERT IGNORE INTO shop_order_params (order_id, name, value)
            SELECT o.id, 'sales_channel', 'backend:'
            FROM shop_order AS o
                LEFT JOIN shop_order_params AS op
                    ON o.id = op.order_id
                        AND op.name = 'sales_channel'
            WHERE op.order_id IS NULL";
    $m->exec($sql);
} catch (Exception $e) {
    waLog::log('Unable to set "sales_channel" => "backend:" params for old orders');
    waLog::log($e->getMessage());
}
