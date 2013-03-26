<?php
$sqls = array();
$sqls[] = "SET @sort := 0, @item_type := ''";
$sqls[] = "UPDATE `shop_plugin` SET
`sort`=(@sort := IF(@item_type != `type`, 0, @sort +1)),
`type` = (@item_type := `type`)
ORDER BY `type`,`sort`";

$model = new waModel();
foreach ($sqls as $sql) {
    try {
        $model->exec($sql);
    } catch (waDbException $e) {
        if (class_exists('waLog')) {
            waLog::log(basename(__FILE__).': '.$e->getMessage(), 'shop-update.log');
        }
        throw $e;
    }
}
