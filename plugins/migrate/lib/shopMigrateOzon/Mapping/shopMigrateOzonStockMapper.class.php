<?php

class shopMigrateOzonStockMapper
{
    private $repository;
    private $settings;
    private $stock_model;
    private $map_model;
    private $map = array();
    private $warehouses = array();

    public function __construct(shopMigrateOzonSnapshotRepository $repository, shopMigrateOzonSettings $settings)
    {
        $this->repository = $repository;
        $this->settings = $settings;
        $this->stock_model = new shopStockModel();
        $this->map_model = $repository->getStockMapModel();
    }

    public function warmup($snapshot_id)
    {
        $this->map = $this->map_model->getMap($snapshot_id);
        $this->warehouses = $this->repository->getWarehousesModel()->getAllBySnapshot($snapshot_id);
    }

    public function resolve($snapshot_id, $warehouse_id)
    {
        $mode = $this->settings->getOperationMode();
        if ($mode === shopMigrateOzonSettings::MODE_MANUAL && isset($this->map[$warehouse_id])) {
            $option = $this->map[$warehouse_id];
            if (isset($option['action']) && $option['action'] === 'skip') {
                return null;
            }
            if (!empty($option['shop_stock_id'])) {
                return (int) $option['shop_stock_id'];
            }
        }

        if (!empty($this->map[$warehouse_id]['shop_stock_id']) && $this->map[$warehouse_id]['mode'] === shopMigrateOzonSettings::MODE_AUTO) {
            return (int) $this->map[$warehouse_id]['shop_stock_id'];
        }

        $stock_id = $this->ensureStockExists($warehouse_id);
        if ($stock_id) {
            $this->map_model->saveAuto($snapshot_id, $warehouse_id, array(
                'mode'          => shopMigrateOzonSettings::MODE_AUTO,
                'shop_stock_id' => $stock_id,
                'action'        => 'auto',
            ));
            $this->map[$warehouse_id] = array(
                'shop_stock_id' => $stock_id,
                'mode'          => shopMigrateOzonSettings::MODE_AUTO,
            );
        }

        return $stock_id;
    }

    private function ensureStockExists($warehouse_id)
    {
        if (empty($this->warehouses[$warehouse_id])) {
            return null;
        }
        $warehouse = $this->warehouses[$warehouse_id];
        $name = $warehouse['name'] ?: ('Ozon '.$warehouse_id);

        $existing = $this->stock_model->getByField('name', $name);
        if ($existing) {
            return (int) $existing['id'];
        }

        $id = $this->stock_model->insert(array(
            'name' => $name,
            'sort' => (int) $this->stock_model->select('MAX(sort) sort')->fetchField('sort') + 1,
        ));

        return (int) $id;
    }
}
