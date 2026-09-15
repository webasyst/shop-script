<?php

class shopMigratePluginWbSnapshotRepository
{
    const MAX_ERROR_MESSAGE_LENGTH = 4000;

    private $snapshots_model;
    private $parents_model;
    private $subjects_model;
    private $cards_model;
    private $sizes_model;
    private $attributes_model;
    private $attribute_values_model;
    private $prices_model;
    private $warehouses_model;
    private $stocks_model;
    private $type_map_model;
    private $category_map_model;
    private $feature_map_model;
    private $stock_map_model;
    private $product_map_model;
    private $sku_map_model;
    private $image_map_model;
    private $runs_model;
    private $unpriced_products_model;

    public function __construct()
    {
        $this->snapshots_model = new shopMigratePluginWbSnapshotsModel();
        $this->parents_model = new shopMigratePluginWbParentsModel();
        $this->subjects_model = new shopMigratePluginWbSubjectsModel();
        $this->cards_model = new shopMigratePluginWbCardsModel();
        $this->sizes_model = new shopMigratePluginWbSizesModel();
        $this->attributes_model = new shopMigratePluginWbAttributesModel();
        $this->attribute_values_model = new shopMigratePluginWbAttributeValuesModel();
        $this->prices_model = new shopMigratePluginWbPricesModel();
        $this->warehouses_model = new shopMigratePluginWbWarehousesModel();
        $this->stocks_model = new shopMigratePluginWbStocksModel();
        $this->type_map_model = new shopMigratePluginWbTypeMapModel();
        $this->category_map_model = new shopMigratePluginWbCategoryMapModel();
        $this->feature_map_model = new shopMigratePluginWbFeatureMapModel();
        $this->stock_map_model = new shopMigratePluginWbStockMapModel();
        $this->product_map_model = new shopMigratePluginWbProductMapModel();
        $this->sku_map_model = new shopMigratePluginWbSkuMapModel();
        $this->image_map_model = new shopMigratePluginWbImageMapModel();
        $this->runs_model = new shopMigratePluginWbRunsModel();
        $this->unpriced_products_model = new shopMigratePluginWbUnpricedProductsModel();
    }

    public function createBuildingSnapshot(array $meta = array(), $phase = 'cards')
    {
        return $this->snapshots_model->createBuilding($meta, $phase);
    }

    public function saveBuildState($snapshot_id, $phase, array $meta)
    {
        $this->snapshots_model->updateState($snapshot_id, 'building', $phase, $meta);
    }

    public function markReady($snapshot_id, array $meta, $phase = 'done')
    {
        $this->snapshots_model->updateState($snapshot_id, 'ready', $phase, $meta);
    }

    public function markFailed($snapshot_id, $message, array $meta = array())
    {
        $meta['error'] = $this->truncateMessage($message);
        $this->snapshots_model->updateState($snapshot_id, 'failed', 'failed', $meta);
    }

    public function dropSnapshotData($snapshot_id)
    {
        $models = array(
            $this->parents_model,
            $this->subjects_model,
            $this->cards_model,
            $this->sizes_model,
            $this->attributes_model,
            $this->attribute_values_model,
            $this->prices_model,
            $this->warehouses_model,
            $this->stocks_model,
            $this->type_map_model,
            $this->category_map_model,
            $this->feature_map_model,
            $this->stock_map_model,
            $this->unpriced_products_model,
            $this->runs_model,
        );
        foreach ($models as $model) {
            $model->deleteBySnapshot($snapshot_id);
        }
    }

    /**
     * Carries mappings into a refreshed snapshot by stable WB identifiers.
     * INSERT IGNORE keeps the method idempotent and never overwrites choices
     * already made for the target snapshot.
     */
    public function copyMappings($source_snapshot_id, $target_snapshot_id)
    {
        $source_snapshot_id = (int) $source_snapshot_id;
        $target_snapshot_id = (int) $target_snapshot_id;
        if ($source_snapshot_id <= 0 || $target_snapshot_id <= 0 || $source_snapshot_id === $target_snapshot_id) {
            return;
        }
        $source = $this->snapshots_model->getByIdSafe($source_snapshot_id);
        if (!$source || (string) ifset($source['status'], '') !== 'ready') {
            return;
        }

        $specifications = array(
            array($this->type_map_model, array(
                'parent_id', 'subject_id', 'mode', 'action', 'shop_type_id', 'shop_type_name',
            ), " AND `action` <> 'create'"),
            array($this->category_map_model, array(
                'entity_type', 'wb_id', 'parent_id', 'mode', 'action', 'shop_category_id',
            ), ''),
            array($this->feature_map_model, array(
                'subject_id', 'characteristic_id', 'mode', 'action', 'shop_feature_id', 'shop_feature_code',
            ), " AND `mode` = 'auto' AND `action` = 'auto'"),
            array($this->stock_map_model, array(
                'warehouse_key', 'mode', 'action', 'shop_stock_id',
            ), " AND `action` <> 'create' AND (`warehouse_key` LIKE 'seller:%' OR `warehouse_key` LIKE 'wb:%')"),
        );
        foreach ($specifications as $specification) {
            /** @var shopMigratePluginWbModel $model */
            $model = $specification[0];
            $fields = $specification[1];
            $extra_where = (string) ifset($specification[2], '');
            $quoted_fields = array();
            foreach ($fields as $field) {
                $quoted_fields[] = '`'.$field.'`';
            }
            $field_sql = implode(', ', $quoted_fields);
            $table = $model->getTableName();
            $model->exec(
                "INSERT IGNORE INTO `{$table}`
                    (`snapshot_id`, {$field_sql}, `created_at`, `updated_at`)
                 SELECT i:target_snapshot_id, {$field_sql}, NOW(), NOW()
                 FROM `{$table}`
                  WHERE `snapshot_id` = i:source_snapshot_id{$extra_where}",
                array(
                    'source_snapshot_id' => $source_snapshot_id,
                    'target_snapshot_id' => $target_snapshot_id,
                )
            );
        }
    }

    /**
     * Bounds raw catalog storage while retaining recent history and every
     * snapshot that still has a resumable import run.
     */
    public function pruneSnapshots(array $keep_snapshot_ids = array(), $keep_latest = 2)
    {
        $keep = array();
        foreach ($keep_snapshot_ids as $snapshot_id) {
            $snapshot_id = (int) $snapshot_id;
            if ($snapshot_id > 0) {
                $keep[$snapshot_id] = true;
            }
        }

        $snapshot_rows = $this->snapshots_model->select('id, status')->order('id DESC')->fetchAll();
        $snapshot_ids = array();
        $ready_kept = 0;
        $ready_limit = max(1, (int) $keep_latest);
        foreach ($snapshot_rows as $snapshot) {
            $snapshot_id = (int) $snapshot['id'];
            $snapshot_ids[] = $snapshot_id;
            if ((string) $snapshot['status'] === 'ready' && $ready_kept < $ready_limit) {
                $keep[$snapshot_id] = true;
                $ready_kept++;
            }
        }
        $active_snapshot_ids = $this->runs_model->select('DISTINCT snapshot_id')
            ->where("status IN ('running', 'cancel_requested')")
            ->fetchAll(null, true);
        foreach ($active_snapshot_ids as $snapshot_id) {
            $keep[(int) $snapshot_id] = true;
        }

        foreach ($snapshot_ids as $snapshot_id) {
            $snapshot_id = (int) $snapshot_id;
            if ($snapshot_id <= 0 || isset($keep[$snapshot_id])) {
                continue;
            }
            $this->dropSnapshotData($snapshot_id);
            $this->snapshots_model->deleteById($snapshot_id);
        }
    }

    private function truncateMessage($message)
    {
        $message = (string) $message;
        if (strlen($message) <= self::MAX_ERROR_MESSAGE_LENGTH) {
            return $message;
        }
        return substr($message, 0, self::MAX_ERROR_MESSAGE_LENGTH).'...';
    }

    public function getSnapshotsModel() { return $this->snapshots_model; }
    public function getParentsModel() { return $this->parents_model; }
    public function getSubjectsModel() { return $this->subjects_model; }
    public function getCardsModel() { return $this->cards_model; }
    public function getSizesModel() { return $this->sizes_model; }
    public function getAttributesModel() { return $this->attributes_model; }
    public function getAttributeValuesModel() { return $this->attribute_values_model; }
    public function getPricesModel() { return $this->prices_model; }
    public function getWarehousesModel() { return $this->warehouses_model; }
    public function getStocksModel() { return $this->stocks_model; }
    public function getTypeMapModel() { return $this->type_map_model; }
    public function getCategoryMapModel() { return $this->category_map_model; }
    public function getFeatureMapModel() { return $this->feature_map_model; }
    public function getStockMapModel() { return $this->stock_map_model; }
    public function getProductMapModel() { return $this->product_map_model; }
    public function getSkuMapModel() { return $this->sku_map_model; }
    public function getImageMapModel() { return $this->image_map_model; }
    public function getRunsModel() { return $this->runs_model; }
}
