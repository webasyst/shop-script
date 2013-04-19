<?php
//1366013433.php
$sqls = array();
$sqls['shop_product_images.original_filename'] = 'ALTER TABLE  `shop_product_images` ADD  `original_filename` VARCHAR( 255 ) NULL AFTER  `size`';

$model = new waModel();
foreach ($sqls as $field => $sql) {
    try {
        list($table, $field) = explode('.', $field, 2);
        $sql_check = 'SELECT `%s` FROM `%s` WHERE 0';
        $model->query(sprintf($sql_check, $field, $table));
    } catch (waDbException $ex) {
        try {
            $model->query($sql);
        } catch (waDbException $e) {
            if (class_exists('waLog')) {
                waLog::log(basename(__FILE__).': '.$e->getMessage(), 'shop-update.log');
            }
            throw $e;
        }
    }
}

$sqls = array();
$sqls[] = 'UPDATE `shop_product_images` SET `original_filename`=`description`
WHERE (`original_filename` IS NULL)
AND (`description` LIKE CONCAT("%.",`ext`))';
$sqls[] = 'UPDATE `shop_product_images` SET `original_filename`=CONCAT(`id`,".",`ext`)
WHERE (`original_filename` IS NULL)';
foreach ($sqls as $sql) {
    try {
        $model->query($sql);
    } catch (waDbException $e) {
        if (class_exists('waLog')) {
            waLog::log(basename(__FILE__).': '.$e->getMessage(), 'shop-update.log');
        }
        throw $e;
    }
}
$drop = array();
$drop['shop_product_images.name'] = 'ALTER TABLE  `shop_product_images` DROP `name`';
foreach ($drop as $field => $sql) {
    try {
        list($table, $field) = explode('.', $field, 2);
        $sql_check = 'SELECT `%s` FROM `%s` WHERE 0';
        $model->query(sprintf($sql_check, $field, $table));
        try {
            $model->query($sql);
        } catch (waDbException $e) {
            if (class_exists('waLog')) {
                waLog::log(basename(__FILE__).': '.$e->getMessage(), 'shop-update.log');
            }
            throw $e;
        }
    } catch (waDbException $ex) {

    }
}
