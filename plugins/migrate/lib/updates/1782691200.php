<?php

$model = new waModel();
$table = 'shop_migrate_ozon_snapshots';

try {
    $model->exec("ALTER TABLE {$table} MODIFY meta LONGTEXT NULL");
} catch (Exception $e) {
    // Keep update idempotent.
}
