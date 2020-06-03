<?php
$m = new waModel();
$sql = <<<SQL
UPDATE shop_feature SET available_for_sku = 1 WHERE code='weight'
SQL;
$m->query($sql);
