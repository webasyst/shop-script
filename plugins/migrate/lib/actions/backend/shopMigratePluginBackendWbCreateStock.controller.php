<?php

class shopMigratePluginBackendWbCreateStockController extends waJsonController
{
    public function execute()
    {
        try {
            if (!$this->getUser()->getRights('shop', 'importexport')) {
                throw new waRightsException(_wp('Access denied.'));
            }
            if (waRequest::method() !== 'post') {
                throw new waRightsException(_wp('Access denied.'));
            }
            $snapshot_id = $this->getValidatedSnapshotId();
            if (!$this->getUser()->getRights('shop', 'settings')) {
                throw new waRightsException(_wp('Access denied.'));
            }
            $warehouse_key = waRequest::post('warehouse_key', '', waRequest::TYPE_STRING_TRIM);
            if ($warehouse_key === '' || strlen($warehouse_key) > 80) {
                throw new waException(_wp('Invalid Wildberries warehouse key.'));
            }
            $warehouse = (new shopMigratePluginWbWarehousesModel())->getByField(array(
                'snapshot_id'   => $snapshot_id,
                'warehouse_key' => $warehouse_key,
                'source'        => array('seller', 'wb'),
            ));
            if (!$warehouse) {
                throw new waException(_wp('Wildberries warehouse was not found in the current snapshot.'));
            }
            $name = waRequest::post('name', '', waRequest::TYPE_STRING_TRIM);
            if ($name === '') {
                throw new waException(_wp('Enter a stock name.'));
            }
            $lock = new shopMigratePluginWbOperationLock();
            $lock_key = 'stock_name_'.substr(sha1($this->normalizeName($name)), 0, 32);
            if (!$lock->acquire($lock_key)) {
                throw new waException(_wp('Another stock with this name is being created. Try again in a moment.'));
            }
            try {
                $model = new shopStockModel();
                $existing = $this->findStockByNormalizedName($model, $name);
                if ($existing) {
                    $this->saveMapping($snapshot_id, $warehouse_key, $existing['id']);
                    $this->response = $this->buildResponse($existing, false);
                    return;
                }
                $id = $model->add(array(
                    'name'           => $name,
                    'low_count'      => shopStockModel::LOW_DEFAULT,
                    'critical_count' => shopStockModel::CRITICAL_DEFAULT,
                ));
                if (!$id) {
                    throw new waException(_wp('Unable to create the Shop-Script stock.'));
                }
                $stock = $model->getById($id);
                if (!$stock) {
                    throw new waException(_wp('The newly created Shop-Script stock was not found.'));
                }
                $this->saveMapping($snapshot_id, $warehouse_key, $stock['id']);
                $this->response = $this->buildResponse($stock, true);
            } finally {
                $lock->release($lock_key);
            }
        } catch (Throwable $e) {
            $this->setError($e->getMessage());
        }
    }

    private function getValidatedSnapshotId()
    {
        $posted_id = waRequest::post('snapshot_id', 0, waRequest::TYPE_INT);
        $current_id = (new shopMigratePluginWbSettings())->getCurrentSnapshotId();
        if ($posted_id <= 0 || $current_id <= 0 || $posted_id !== $current_id) {
            throw new waException(_wp('The Wildberries snapshot has changed. Reload the page and try again.'));
        }
        return $posted_id;
    }

    private function saveMapping($snapshot_id, $warehouse_key, $stock_id)
    {
        (new shopMigratePluginWbStockMapModel())->saveMapping($snapshot_id, $warehouse_key, array(
            'mode'          => shopMigratePluginWbSettings::MODE_MANUAL,
            'action'        => 'map',
            'shop_stock_id' => (int) $stock_id,
        ));
    }

    private function findStockByNormalizedName(shopStockModel $model, $name)
    {
        $normalized = $this->normalizeName($name);
        foreach ((array) $model->getAll() as $stock) {
            if ($this->normalizeName(ifset($stock['name'], '')) === $normalized) {
                return $stock;
            }
        }
        return null;
    }

    private function normalizeName($name)
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) $name));
        $name = $name === null ? '' : $name;
        return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    }

    private function buildResponse(array $stock, $created)
    {
        return array(
            'status'        => 'ok',
            'stock'         => $stock,
            'stock_id'      => (int) $stock['id'],
            'shop_stock_id' => (int) $stock['id'],
            'created'       => (bool) $created,
            'reused'        => !$created,
        );
    }
}
