<?php

class shopMigratePluginOzonStockMapper
{
    private $repository;
    private $settings;
    private $stock_model;
    private $map_model;
    private $map = array();
    private $warehouses = array();
    private $resolved_shop_stock_ids = array();

    public function __construct(shopMigratePluginOzonSnapshotRepository $repository, shopMigratePluginOzonSettings $settings)
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
        $this->resolved_shop_stock_ids = array();
        foreach ($this->map as $item) {
            if (!empty($item['shop_stock_id'])) {
                $this->rememberResolvedStock($item['shop_stock_id']);
            }
        }
        if ($this->settings->getOperationMode() === shopMigratePluginOzonSettings::MODE_AUTO) {
            $this->ensureAllWarehousesMapped($snapshot_id);
        }
    }

    public function resolve($snapshot_id, $warehouse_id)
    {
        $mode = $this->settings->getOperationMode();
        if ($mode === shopMigratePluginOzonSettings::MODE_MANUAL && isset($this->map[$warehouse_id])) {
            $option = $this->map[$warehouse_id];
            if (isset($option['action']) && $option['action'] === 'skip') {
                return null;
            }
            if (!empty($option['shop_stock_id'])) {
                $stock_id = (int) $option['shop_stock_id'];
                $this->rememberResolvedStock($stock_id);
                return $stock_id;
            }
        }

        if (!empty($this->map[$warehouse_id]['shop_stock_id']) && $this->map[$warehouse_id]['mode'] === shopMigratePluginOzonSettings::MODE_AUTO) {
            $stock_id = (int) $this->map[$warehouse_id]['shop_stock_id'];
            $this->rememberResolvedStock($stock_id);
            return $stock_id;
        }

        $stock_id = $this->ensureStockExists($warehouse_id);
        if ($stock_id) {
            $this->map_model->saveAuto($snapshot_id, $warehouse_id, array(
                'mode'          => shopMigratePluginOzonSettings::MODE_AUTO,
                'shop_stock_id' => $stock_id,
                'action'        => 'auto',
            ));
            $this->map[$warehouse_id] = array(
                'shop_stock_id' => $stock_id,
                'mode'          => shopMigratePluginOzonSettings::MODE_AUTO,
            );
            $this->rememberResolvedStock($stock_id);
        }

        return $stock_id;
    }

    public function getResolvedShopStockIds()
    {
        return array_values($this->resolved_shop_stock_ids);
    }

    private function ensureAllWarehousesMapped($snapshot_id)
    {
        foreach ($this->warehouses as $warehouse_id => $warehouse) {
            $warehouse_id = (int) $warehouse_id;
            if (!empty($this->map[$warehouse_id]['shop_stock_id'])) {
                $this->rememberResolvedStock($this->map[$warehouse_id]['shop_stock_id']);
                continue;
            }
            $stock_id = $this->ensureStockExists($warehouse_id);
            if (!$stock_id) {
                continue;
            }
            $this->map_model->saveAuto($snapshot_id, $warehouse_id, array(
                'mode'          => shopMigratePluginOzonSettings::MODE_AUTO,
                'shop_stock_id' => $stock_id,
                'action'        => 'auto',
            ));
            $this->map[$warehouse_id] = array(
                'shop_stock_id' => $stock_id,
                'mode'          => shopMigratePluginOzonSettings::MODE_AUTO,
                'action'        => 'auto',
            );
            $this->rememberResolvedStock($stock_id);
        }
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
            'name'           => $name,
            'sort'           => (int) $this->stock_model->select('MAX(sort) sort')->fetchField('sort') + 1,
            'low_count'      => shopStockModel::LOW_DEFAULT,
            'critical_count' => shopStockModel::CRITICAL_DEFAULT,
        ));

        return (int) $id;
    }

    private function rememberResolvedStock($stock_id)
    {
        $stock_id = (int) $stock_id;
        if ($stock_id > 0) {
            $this->resolved_shop_stock_ids[$stock_id] = $stock_id;
        }
    }
}
