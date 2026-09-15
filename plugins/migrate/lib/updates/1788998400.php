<?php

$db = include wa()->getAppPath('plugins/migrate/lib/config/db.php', 'shop');
$table = 'shop_migrate_wb_unpriced_products';
// A later plugin version may remove this table from its schema.
if (isset($db[$table])) {
    (new waModel())->createSchema(array($table => $db[$table]));
}
