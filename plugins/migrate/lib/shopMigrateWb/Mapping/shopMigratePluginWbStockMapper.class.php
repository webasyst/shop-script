<?php

class shopMigratePluginWbStockMapper
{
    const MODE_AUTO = 'auto';
    const MODE_MANUAL = 'manual';

    private $repository;
    private $stock_model;
    private $map_model;
    private $map = array();
    private $snapshot_id = 0;
    private $resolved_shop_stock_ids = array();

    public function __construct(shopMigratePluginWbSnapshotRepository $repository)
    {
        $this->repository = $repository;
        $this->stock_model = new shopStockModel();
        $this->map_model = $repository->getStockMapModel();
    }

    public function warmup($snapshot_id)
    {
        $snapshot_id = $this->requirePositiveId($snapshot_id, _wp('Invalid Wildberries snapshot ID.'));
        $this->map = array();
        $this->resolved_shop_stock_ids = array();
        foreach ((array) $this->map_model->getMap($snapshot_id) as $row) {
            if (!is_array($row) || empty($row['warehouse_key'])) {
                continue;
            }
            $key = (string) $row['warehouse_key'];
            if (strpos($key, 'seller:') !== 0 && strpos($key, 'wb:') !== 0) {
                continue;
            }
            $this->map[$key] = $row;
            if (!empty($row['shop_stock_id'])) {
                $stock = $this->stock_model->getById((int) $row['shop_stock_id']);
                if ($stock) {
                    $this->rememberResolvedStock($stock['id']);
                }
            }
        }
        $this->snapshot_id = $snapshot_id;
    }

    public function resolve($snapshot_id, $warehouse_key, $mode = self::MODE_AUTO)
    {
        $snapshot_id = $this->requirePositiveId($snapshot_id, _wp('Invalid Wildberries snapshot ID.'));
        $warehouse_key = $this->requireWarehouseKey($warehouse_key);
        $this->ensureWarm($snapshot_id);
        $mode = $this->normalizeMode($mode);
        $mapping = isset($this->map[$warehouse_key]) ? $this->map[$warehouse_key] : array();

        if ($mode === self::MODE_MANUAL && $this->isManualMapping($mapping)) {
            $legacy_action = strtolower(trim((string) ifset($mapping['action'], 'auto')));
            $action = $this->getAction($mapping);
            if ($action === 'skip') {
                return null;
            }
            if ($legacy_action !== 'create' && !empty($mapping['shop_stock_id'])) {
                $stock_id = $this->requireMappedStock($mapping['shop_stock_id']);
                $this->rememberResolvedStock($stock_id);
                return $stock_id;
            }
            if ($action !== 'auto') {
                throw new waException(_wp('Select a Shop-Script stock for the manual Wildberries mapping.'));
            }
        }

        if ($this->isAutoMapping($mapping) && !empty($mapping['shop_stock_id'])) {
            $stock = $this->stock_model->getById((int) $mapping['shop_stock_id']);
            if ($stock) {
                $this->rememberResolvedStock($stock['id']);
                return (int) $stock['id'];
            }
        }

        $warehouse = $this->getSourceWarehouse($snapshot_id, $warehouse_key);
        $stock = $this->findExactStock($warehouse['name']);
        $stock_id = $stock ? (int) $stock['id'] : $this->createStock($warehouse['name']);
        $this->saveMapping($snapshot_id, $warehouse_key, array(
            'mode'          => self::MODE_AUTO,
            'action'        => 'auto',
            'shop_stock_id' => $stock_id,
        ));
        $this->rememberResolvedStock($stock_id);
        return $stock_id;
    }

    /**
     * Resolves legacy callers through the automatic strategy.
     */
    public function createForWarehouse($snapshot_id, $warehouse_key)
    {
        return $this->resolve($snapshot_id, $warehouse_key, self::MODE_AUTO);
    }

    /**
     * Uses the native Shop-Script method so existing SKUs receive stock rows.
     */
    public function createStock($name)
    {
        $name = $this->cleanName($name);
        if ($name === '') {
            throw new waException(_wp('Enter a Shop-Script stock name.'));
        }
        $stock_id = (int) $this->stock_model->add(array(
            'name'           => $name,
            'low_count'      => shopStockModel::LOW_DEFAULT,
            'critical_count' => shopStockModel::CRITICAL_DEFAULT,
        ));
        if ($stock_id <= 0) {
            throw new waException(_wp('Unable to create a Shop-Script stock.'));
        }
        return $stock_id;
    }

    public function getResolvedShopStockIds()
    {
        return array_values($this->resolved_shop_stock_ids);
    }

    private function getSourceWarehouse($snapshot_id, $warehouse_key)
    {
        $warehouse = $this->repository->getWarehousesModel()->getByField(array(
            'snapshot_id'   => (int) $snapshot_id,
            'warehouse_key' => (string) $warehouse_key,
            'source'        => array('seller', 'wb'),
        ));
        if (!$warehouse) {
            throw new waException(_wp('Wildberries warehouse was not found in the current snapshot.'));
        }
        $name = $this->cleanName(ifset($warehouse['name'], ''));
        if ($name === '') {
            $name = sprintf(_wp('Wildberries warehouse %s'), $warehouse_key);
        }
        $warehouse['name'] = $name;
        return $warehouse;
    }

    private function findExactStock($name)
    {
        $normalized = $this->normalizeName($name);
        foreach ((array) $this->stock_model->getAll() as $stock) {
            if ($this->normalizeName(ifset($stock['name'], '')) === $normalized) {
                return $stock;
            }
        }
        return null;
    }

    private function requireMappedStock($stock_id)
    {
        $stock = $this->stock_model->getById((int) $stock_id);
        if (!$stock) {
            throw new waException(_wp('The mapped Shop-Script stock no longer exists.'));
        }
        return (int) $stock['id'];
    }

    private function saveMapping($snapshot_id, $warehouse_key, array $data)
    {
        $this->map_model->saveMapping($snapshot_id, $warehouse_key, $data);
        $this->map[(string) $warehouse_key] = array_merge(array(
            'snapshot_id'   => (int) $snapshot_id,
            'warehouse_key' => (string) $warehouse_key,
        ), $data);
    }

    private function rememberResolvedStock($stock_id)
    {
        $stock_id = (int) $stock_id;
        if ($stock_id > 0) {
            $this->resolved_shop_stock_ids[$stock_id] = $stock_id;
        }
    }

    private function ensureWarm($snapshot_id)
    {
        if ((int) $this->snapshot_id !== (int) $snapshot_id) {
            $this->warmup($snapshot_id);
        }
    }

    private function requireWarehouseKey($warehouse_key)
    {
        $warehouse_key = trim((string) $warehouse_key);
        if ($warehouse_key === '') {
            throw new waException(_wp('Invalid Wildberries warehouse key.'));
        }
        return $warehouse_key;
    }

    private function isManualMapping(array $mapping)
    {
        return ifset($mapping['mode'], '') === self::MODE_MANUAL;
    }

    private function isAutoMapping(array $mapping)
    {
        return ifset($mapping['mode'], '') === self::MODE_AUTO;
    }

    private function getAction(array $mapping)
    {
        $action = strtolower(trim((string) ifset($mapping['action'], 'auto')));
        if ($action === 'create') {
            return 'auto';
        }
        return $action === '' ? 'auto' : $action;
    }

    private function normalizeMode($mode)
    {
        return strtolower(trim((string) $mode)) === self::MODE_MANUAL ? self::MODE_MANUAL : self::MODE_AUTO;
    }

    private function requirePositiveId($value, $message)
    {
        $value = (int) $value;
        if ($value <= 0) {
            throw new waException($message);
        }
        return $value;
    }

    private function cleanName($name)
    {
        $name = trim((string) $name);
        $clean = preg_replace('/\s+/u', ' ', $name);
        return $clean === null ? $name : $clean;
    }

    private function normalizeName($name)
    {
        $name = $this->cleanName($name);
        return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    }
}
