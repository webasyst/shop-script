<?php

$sql_set = "SET @sort := 0, @context := ''";

$sql_context = 'UPDATE `%1$s` SET
`sort`=(@sort := IF(@context != `%3$s`, 0, @sort +1)),
`%3$s` = (@context := `%3$s`)
ORDER BY `%3$s`,`sort`,`%2$s`';

$sql_single = 'UPDATE `%1$s` SET
`sort`=(@sort := @sort +1)
ORDER BY `sort`,`%2$s`';

$tables = array(
    'shop_plugin'                   => 'shopPluginModel',
    'shop_product_skus'             => 'shopProductSkusModel',
    'shop_type'                     => 'shopTypeModel',
    'shop_type_features'            => 'shopTypeFeaturesModel',
    'shop_feature_values_dimension' => 'shopFeatureValuesDimensionModel',
    'shop_feature_values_double'    => 'shopFeatureValuesDoubleModel',
    'shop_feature_values_text'      => 'shopFeatureValuesTextModel',
    'shop_feature_values_varchar'   => 'shopFeatureValuesVarcharModel',
);

foreach ($tables as $table => $table_model) {
    /**
     * @var shopSortableModel
     */
    if (class_exists($table_model)) {
        $model = new $table_model();
        try {
            $id = $model->getTableId();
            if (is_array($id)) {
                $id = implode('`, `', $id);
            }
            if ($context = $model->getTableContext()) {
                $sql = sprintf($sql_context, $model->getTableName(), $id, $context);
            } else {
                $sql = sprintf($sql_single, $model->getTableName(), $id);
            }
            $model->exec($sql_set);
            $model->exec($sql);
        } catch (waDbException $e) {
            if (class_exists('waLog')) {
                waLog::log(basename(__FILE__).': '.$e->getMessage(), 'shop-update.log');
            }
            throw $e;
        }
    }
}

$sqls = array();
$sqls[] = "SET @sort := 0, @context := ''";
$sqls[] = "UPDATE `shop_product_images` SET
`sort`=(@sort := IF(@context != `product_id`, 0, @sort +1)),
`product_id` = (@context := `product_id`)
ORDER BY `product_id`,`sort`,`id`";

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
