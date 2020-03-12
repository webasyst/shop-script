<?php
$m = new waModel();

$path = wa()->getConfig()->getAppsPath('shop', 'lib/config/db.php');
if (file_exists($path)) {
    $schema = include($path);
    $m->addColumn('available_for_sku', $schema, 'count', 'shop_feature');
}
