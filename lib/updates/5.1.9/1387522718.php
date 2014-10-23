<?php

$model = new waModel();

try {
    $model->query("SELECT sort FROM shop_service WHERE 0");
} catch (waDbException $e) {
    $sql = 'ALTER TABLE `shop_service` ADD `sort` INT(11) NOT NULL DEFAULT 0';
    $model->exec($sql);
}

$i = 0;
foreach ($model->query("SELECT * FROM shop_service ORDER BY sort, id") as $item)
{
    $model->exec("UPDATE shop_service SET sort = $i WHERE id = ".$item['id']);
    $i++;
}

try {
    $model->query("SELECT sort FROM shop_service_variants WHERE 0");
} catch (Exception $e) {
    $model->exec("ALTER TABLE shop_service_variants ADD COLUMN `sort` INT(11) NOT NULL DEFAULT 0");
    $model->exec("UPDATE shop_service_variants SET sort = id");
}