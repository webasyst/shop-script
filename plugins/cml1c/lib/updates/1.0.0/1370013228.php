<?php

$tables = array(
    'shop_product',
    'shop_category',
    'shop_product_skus',
);

$m = new waModel();
foreach ($tables as $table) {
    $sql = "SELECT	COUNT(*) `cnt`, `id_1c`
	FROM	`{$table}`
	WHERE	`id_1c` IS NOT NULL
	GROUP BY	`id_1c`
	HAVING	`cnt`>1";
    $duplicates = $m->query($sql)->fetchAll('id_1c', true);
    foreach ($duplicates as $id => $count) {
        $sql = "UPDATE `{$table}`
	SET `id_1c`= NULL
	WHERE `id_1c` IN (s:0)
	LIMIT i:1";
        $m->query($sql, $id, $count - 1);
    }

}
