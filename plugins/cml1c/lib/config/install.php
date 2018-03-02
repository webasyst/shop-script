<?php
$model = new waModel();
$tables = array(
    'shop_product'      => 'id',
    'shop_product_skus' => 'product_id',
    'shop_category'     => 'id',
);
foreach ($tables as $table => $field) {
    try {
        $sql = "SELECT `id_1c` FROM `{$table}` WHERE 0";
        $model->query($sql);
    } catch (waDbException $ex) {
        $sql = "ALTER TABLE  `{$table}` ADD  `id_1c` VARCHAR( 36 ) NULL DEFAULT NULL AFTER  `{$field}` ,
ADD INDEX `id_1c` (  `id_1c` )";
        $model->query($sql);
    }
}


$tables = array(
    'shop_feature'          => 'code',
    'shop_service'          => 'id',
    'shop_service_variants' => 'service_id',
);
foreach ($tables as $table => $field) {
    try {
        $sql = "SELECT `cml1c_id` FROM `{$table}` WHERE 0";
        $model->query($sql);
    } catch (waDbException $ex) {
        $sql = "ALTER TABLE `{$table}` ADD `cml1c_id` VARCHAR( 36 ) NULL DEFAULT NULL AFTER `{$field}` ,
ADD INDEX `cml1c_id` ( `cml1c_id` )";
        $model->query($sql);
    }
}

$model_types = array(
    shopFeatureModel::TYPE_VARCHAR,
    shopFeatureModel::TYPE_DOUBLE,
    shopFeatureModel::TYPE_TEXT,
    shopFeatureModel::TYPE_DIMENSION,
    shopFeatureModel::TYPE_RANGE,
    shopFeatureModel::TYPE_BOOLEAN,
    shopFeatureModel::TYPE_COLOR,
);

$model = new shopFeatureModel();

foreach ($model_types as $type) {
    $type_model = null;
    try {
        $type_model = $model->getValuesModel($type);
    } catch (waException $ex) {
    }

    if ($type_model && ($table = $type_model->getTableName())) {
        try {
            $type_model->select('cml1c_id')->where('0')->fetchAll();
        } catch (waDbException $ex) {
            $table = $type_model->getTableName();
            $sql = <<<SQL
ALTER TABLE `{$table}` ADD `cml1c_id` VARCHAR( 36 ) NULL DEFAULT NULL,
ADD INDEX `cml1c_id` ( `cml1c_id` )
SQL;
            $type_model->query($sql);
        }
    }
}
