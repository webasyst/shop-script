<?php

$model = new waModel();
$snapshots_table = 'shop_migrate_ozon_snapshots';
$products_table = 'shop_migrate_ozon_products';
$stocks_table = 'shop_migrate_ozon_stocks';

$snapshot_columns = array();
foreach ($model->query("SHOW COLUMNS FROM `{$snapshots_table}`")->fetchAll() as $column) {
    if (!empty($column['Field'])) {
        $snapshot_columns[(string) $column['Field']] = strtolower((string) ifset($column['Type'], ''));
    }
}
if (isset($snapshot_columns['meta']) && strpos($snapshot_columns['meta'], 'longtext') !== 0) {
    $model->exec("ALTER TABLE `{$snapshots_table}` MODIFY `meta` LONGTEXT NULL");
}

$columns = array();
foreach ($model->query("SHOW COLUMNS FROM `{$products_table}`")->fetchAll() as $column) {
    if (!empty($column['Field'])) {
        $columns[(string) $column['Field']] = true;
    }
}

if (empty($columns['ozon_sku'])) {
    $model->exec("ALTER TABLE `{$products_table}` ADD `ozon_sku` VARCHAR(64) NULL AFTER `offer_id`");
}
if (empty($columns['model_id'])) {
    $model->exec("ALTER TABLE `{$products_table}` ADD `model_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 AFTER `type_id`");
}
if (empty($columns['model_count'])) {
    $model->exec("ALTER TABLE `{$products_table}` ADD `model_count` INT(11) UNSIGNED NOT NULL DEFAULT 0 AFTER `model_id`");
}

$product_indexes = array();
foreach ($model->query("SHOW INDEX FROM `{$products_table}`")->fetchAll() as $index) {
    $key_name = (string) ifset($index['Key_name'], '');
    if ($key_name === '') {
        continue;
    }
    $sequence = (int) ifset($index['Seq_in_index'], 0);
    $product_indexes[$key_name][$sequence] = (string) ifset($index['Column_name'], '');
}
if (!empty($product_indexes['snapshot_model'])) {
    ksort($product_indexes['snapshot_model']);
    $product_indexes['snapshot_model'] = array_values($product_indexes['snapshot_model']);
}
$required_product_index = array('snapshot_id', 'model_id', 'product_id');
if (ifset($product_indexes['snapshot_model'], array()) !== $required_product_index) {
    if (!empty($product_indexes['snapshot_model'])) {
        $model->exec("ALTER TABLE `{$products_table}` DROP INDEX `snapshot_model`");
    }
    $model->exec(
        "ALTER TABLE `{$products_table}` ADD INDEX `snapshot_model` (`snapshot_id`, `model_id`, `product_id`)"
    );
}

$model->exec(
    "DELETE older FROM `{$stocks_table}` older
     INNER JOIN `{$stocks_table}` newer
         ON newer.snapshot_id = older.snapshot_id
        AND newer.product_id = older.product_id
        AND newer.warehouse_id = older.warehouse_id
        AND newer.id > older.id"
);

$stock_index_columns = array();
$stock_index_is_unique = false;
foreach ($model->query("SHOW INDEX FROM `{$stocks_table}`")->fetchAll() as $index) {
    if ((string) ifset($index['Key_name'], '') !== 'snapshot_stock') {
        continue;
    }
    $stock_index_columns[(int) ifset($index['Seq_in_index'], 0)] = (string) ifset($index['Column_name'], '');
    $stock_index_is_unique = (int) ifset($index['Non_unique'], 1) === 0;
}
ksort($stock_index_columns);
$stock_index_columns = array_values($stock_index_columns);
$required_stock_index = array('snapshot_id', 'product_id', 'warehouse_id');
if ($stock_index_columns !== $required_stock_index || !$stock_index_is_unique) {
    if ($stock_index_columns) {
        $model->exec("ALTER TABLE `{$stocks_table}` DROP INDEX `snapshot_stock`");
    }
    $model->exec(
        "ALTER TABLE `{$stocks_table}`
         ADD UNIQUE INDEX `snapshot_stock` (`snapshot_id`, `product_id`, `warehouse_id`)"
    );
}

try {
    $model->exec(
        "UPDATE `{$products_table}`
         SET `model_id` = COALESCE(
                 CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`details`, '$.model_info.model_id')), 'null') AS UNSIGNED),
                 0
             ),
             `model_count` = COALESCE(
                 CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`details`, '$.model_info.count')), 'null') AS UNSIGNED),
                 0
             ),
             `ozon_sku` = COALESCE(
                 NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`details`, '$.sku')), 'null'),
                 NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`details`, '$.sources[0].sku')), 'null'),
                 `ozon_sku`
             )
         WHERE `details` IS NOT NULL AND `details` <> ''"
    );
} catch (Throwable $e) {
    // MySQL 5.6 has no JSON_EXTRACT. Backfill old snapshots in bounded PHP chunks.
    $last_id = 0;
    do {
        $rows = $model->query(
            "SELECT `id`, `details`
             FROM `{$products_table}`
             WHERE `id` > i:last_id AND `details` IS NOT NULL AND `details` <> ''
             ORDER BY `id`
             LIMIT 50",
            array('last_id' => $last_id)
        )->fetchAll();
        foreach ($rows as $row) {
            $last_id = max($last_id, (int) $row['id']);
            $details = json_decode((string) $row['details'], true);
            if (!is_array($details)) {
                continue;
            }
            $model_info = isset($details['model_info']) && is_array($details['model_info'])
                ? $details['model_info']
                : array();
            $sku = isset($details['sku']) && $details['sku'] !== '' ? (string) $details['sku'] : null;
            if ($sku === null && !empty($details['sources']) && is_array($details['sources'])) {
                foreach ($details['sources'] as $source) {
                    if (is_array($source) && isset($source['sku']) && $source['sku'] !== '') {
                        $sku = (string) $source['sku'];
                        break;
                    }
                }
            }
            $params = array(
                'model_id'    => (int) ifset($model_info['model_id'], 0),
                'model_count' => (int) ifset($model_info['count'], 0),
                'id'          => (int) $row['id'],
            );
            $set_sku_sql = '';
            if ($sku !== null) {
                $params['ozon_sku'] = $sku;
                $set_sku_sql = ', `ozon_sku` = s:ozon_sku';
            }
            $model->exec(
                "UPDATE `{$products_table}`
                 SET `model_id` = i:model_id,
                     `model_count` = i:model_count{$set_sku_sql}
                 WHERE `id` = i:id",
                $params
            );
        }
    } while ($rows);
}
