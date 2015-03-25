<?php
$model = new shopProductModel();
$sql = "SELECT count(id) FROM `shop_product_skus` WHERE count < 0";
if ($model->query($sql)->fetchField() > 0) {
    $model->correctCount();
}
