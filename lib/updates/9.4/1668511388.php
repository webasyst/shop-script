<?php

$model = new shopCategoryModel();

$sql = "UPDATE `" . $model->getTableName() . "` SET `conditions` = REPLACE(conditions, 'type=', 'type_id=')
        WHERE `type` = " . $model::TYPE_DYNAMIC . " AND `conditions` IS NOT NULL";
$model->exec($sql);
