<?php

$model = new waModel();

// correct tag counters
$sql = "
    UPDATE `shop_tag` t JOIN (
            SELECT t.id, t.name, t.count, COUNT(pt.product_id) AS product_count
            FROM `shop_tag` t
            LEFT JOIN `shop_product_tags` pt ON t.id = pt.tag_id
            GROUP BY t.id
    ) r ON t.id = r.id
    SET t.count = r.product_count
";

$model->exec($sql);


$sql = "
    SELECT DISTINCT pt.tag_id
    FROM `shop_product_tags` pt
    LEFT JOIN `shop_tag` t ON pt.tag_id = t.id
    WHERE t.id IS NULL
";

// delete hanging product-tag items
$ids = array_keys($model->query($sql)->fetchAll('tag_id'));
if ($ids) {
    $shop_product_tag = new shopProductTagsModel();
    $shop_product_tag->deleteByField('tag_id', $ids);
}