<?php

$sql = <<<SQL
UPDATE shop_product_skus SET available = 1 WHERE available > 1
SQL;

$model = new waModel();
$model->exec($sql);
