<?php
$sqls = array();
$sqls[] = <<<SQL
ALTER TABLE  `shop_product_skus`
CHANGE  `name`  `name` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  ''
SQL;


$model = new waModel();

foreach ($sqls as $sql) {
    $model->exec($sql);
}
$fields = array(
    'compare_price_selectable',
    'purchase_price_selectable',
);
foreach ($fields as $field) {
    try {
        $model->query("SELECT `{$field}` FROM `shop_product` WHERE 0");
    } catch (waDbException $e) {
        $model->query("ALTER TABLE `shop_product` ADD {$field} DECIMAL(15, 4) NOT NULL DEFAULT 0");
    }
}