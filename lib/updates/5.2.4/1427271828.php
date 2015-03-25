<?php
$model = new shopProductModel();
$sql = "SELECT id FROM `shop_product_skus` WHERE count IS NULL LIMIT 1";
if ($model->query($sql)->fetchField() > 0) {
    $model->correctCount();
}
