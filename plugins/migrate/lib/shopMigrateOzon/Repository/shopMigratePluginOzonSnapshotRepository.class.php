<?php

class shopMigratePluginOzonSnapshotRepository
{
    private $snapshots_model;
    private $products_model;
    private $categories_model;
    private $attributes_model;
    private $attribute_values_model;
    private $warehouses_model;
    private $stocks_model;
    private $type_map_model;
    private $category_map_model;
    private $stock_map_model;
    private $feature_map_model;
    private $product_map_model;

    public function __construct()
    {
        $this->snapshots_model = new shopMigratePluginOzonSnapshotsModel();
        $this->products_model = new shopMigratePluginOzonProductsModel();
        $this->categories_model = new shopMigratePluginOzonCategoriesModel();
        $this->attributes_model = new shopMigratePluginOzonAttributesModel();
        $this->attribute_values_model = new shopMigratePluginOzonAttributeValuesModel();
        $this->warehouses_model = new shopMigratePluginOzonWarehousesModel();
        $this->stocks_model = new shopMigratePluginOzonStocksModel();
        $this->type_map_model = new shopMigratePluginOzonTypeMapModel();
        $this->category_map_model = new shopMigratePluginOzonCategoryMapModel();
        $this->stock_map_model = new shopMigratePluginOzonStockMapModel();
        $this->feature_map_model = new shopMigratePluginOzonFeatureMapModel();
        $this->product_map_model = new shopMigratePluginOzonProductMapModel();
    }

    public function createSnapshot(array $meta = array())
    {
        return $this->snapshots_model->create('draft', $meta);
    }

    public function markReady($snapshot_id, array $meta = array())
    {
        $this->snapshots_model->updateStatus($snapshot_id, 'ready', $meta);
    }

    public function markFailed($snapshot_id, $message)
    {
        $this->snapshots_model->updateStatus($snapshot_id, 'failed', array('error' => $message));
    }

    public function dropSnapshotData($snapshot_id)
    {
        $this->products_model->deleteBySnapshot($snapshot_id);
        $this->categories_model->deleteBySnapshot($snapshot_id);
        $this->attributes_model->deleteBySnapshot($snapshot_id);
        $this->attribute_values_model->deleteBySnapshot($snapshot_id);
        $this->warehouses_model->deleteBySnapshot($snapshot_id);
        $this->stocks_model->deleteBySnapshot($snapshot_id);
        $this->type_map_model->deleteBySnapshot($snapshot_id);
        $this->category_map_model->deleteBySnapshot($snapshot_id);
        $this->stock_map_model->deleteBySnapshot($snapshot_id);
        $this->feature_map_model->deleteBySnapshot($snapshot_id);
    }

    public function getSnapshotsModel()
    {
        return $this->snapshots_model;
    }

    public function getProductsModel()
    {
        return $this->products_model;
    }

    public function getCategoriesModel()
    {
        return $this->categories_model;
    }

    public function getAttributesModel()
    {
        return $this->attributes_model;
    }

    public function getAttributeValuesModel()
    {
        return $this->attribute_values_model;
    }

    public function getWarehousesModel()
    {
        return $this->warehouses_model;
    }

    public function getStocksModel()
    {
        return $this->stocks_model;
    }

    public function getTypeMapModel()
    {
        return $this->type_map_model;
    }

    public function getCategoryMapModel()
    {
        return $this->category_map_model;
    }

    public function getStockMapModel()
    {
        return $this->stock_map_model;
    }

    public function getFeatureMapModel()
    {
        return $this->feature_map_model;
    }

    public function getProductMapModel()
    {
        return $this->product_map_model;
    }
}

