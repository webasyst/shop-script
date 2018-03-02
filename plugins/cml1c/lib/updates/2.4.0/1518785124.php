<?php

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
