<?php
$sqls[] = <<<SQL
UPDATE shop_product_skus SET available = 1 WHERE available > 1
SQL;
$sqls[] = <<<SQL
ALTER TABLE shop_product_skus MODIFY available tinyint(1) NOT NULL DEFAULT 1
SQL;
$model = new waModel();
foreach ($sqls as $sql) {
    $model->exec($sql);
}
