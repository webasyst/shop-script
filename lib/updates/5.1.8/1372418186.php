<?php


$model = new shopFeatureModel();
try {
    $model->query('SELECT `count` FROM `shop_feature` WHERE 0');
} catch (waDbException $ex) {
    $model->query('ALTER TABLE  `shop_feature`  ADD  `count` INT UNSIGNED NOT NULL');
}

$model->recount();