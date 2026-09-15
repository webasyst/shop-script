<?php

class shopMigratePluginBackendWbStockMapController extends waJsonController
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
            $warehouse_key = waRequest::post('warehouse_key', '', waRequest::TYPE_STRING_TRIM);
            if ($warehouse_key === '') {
                throw new waException(_wp('Invalid Wildberries stock mapping parameters.'));
            }

            $warehouse = (new shopMigratePluginWbWarehousesModel())->getByField(array(
                'snapshot_id'   => $snapshot_id,
                'warehouse_key' => $warehouse_key,
                'source'        => array('seller', 'wb'),
            ));
            if (!$warehouse) {
                throw new waException(_wp('Wildberries warehouse was not found in the current snapshot.'));
            }

            list($action, $shop_stock_id) = $this->getActionAndTarget('shop_stock_id');
            $model = new shopMigratePluginWbStockMapModel();
            $key = array('snapshot_id' => $snapshot_id, 'warehouse_key' => $warehouse_key);
            if ($action === 'auto') {
                $model->deleteByField($key);
                $this->response = array('status' => 'ok', 'action' => 'auto');
                return;
            }

            $stock = null;
            if ($action === 'map') {
                $stock = (new shopStockModel())->getById($shop_stock_id);
                if (!$stock) {
                    throw new waException(_wp('Shop-Script stock was not found.'));
                }
            }
            $data = array(
                'mode'          => shopMigratePluginWbSettings::MODE_MANUAL,
                'action'        => $action,
                'shop_stock_id' => $stock ? (int) $stock['id'] : null,
            );
            $model->saveMapping($snapshot_id, $warehouse_key, $data);
            $this->response = array('status' => 'ok', 'action' => $action, 'stock' => $stock);
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

    private function getActionAndTarget($target_field)
    {
        $action = strtolower(waRequest::post('action', '', waRequest::TYPE_STRING_TRIM));
        $value = waRequest::post('value', '', waRequest::TYPE_STRING_TRIM);
        $target_id = waRequest::post($target_field, 0, waRequest::TYPE_INT);
        if ($target_id <= 0 && is_numeric($value)) {
            $target_id = (int) $value;
        }
        if ($action === '') {
            if ($value === '' || strtolower($value) === 'auto') {
                $action = 'auto';
            } elseif (in_array(strtolower($value), array('create', 'skip'), true)) {
                $action = strtolower($value);
            } elseif (is_numeric($value)) {
                $action = 'map';
            }
        }
        if ($action === 'create') {
            $action = 'auto';
        }
        if (!in_array($action, array('auto', 'skip', 'map'), true)) {
            throw new waException(_wp('Invalid Wildberries stock mapping action.'));
        }
        if ($action === 'map' && $target_id <= 0) {
            throw new waException(_wp('Select a Shop-Script stock.'));
        }
        return array($action, $target_id);
    }
}
