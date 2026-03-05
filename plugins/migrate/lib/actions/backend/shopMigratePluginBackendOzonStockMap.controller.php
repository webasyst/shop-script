<?php

class shopMigratePluginBackendOzonStockMapController extends waJsonController
{
    public function execute()
    {
        try {
            $settings = new shopMigratePluginOzonSettings();
            $snapshot_id = $settings->getCurrentSnapshotId();
            if (!$snapshot_id) {
                throw new waException(_wp('Snapshot is missing.'));
            }

            $warehouse_id = waRequest::post('warehouse_id', 0, waRequest::TYPE_INT);
            $value = waRequest::post('value', '', waRequest::TYPE_STRING_TRIM);

            if (!$warehouse_id) {
                throw new waException(_wp('Invalid parameters.'));
            }

            $model = new shopMigratePluginOzonStockMapModel();
            if ($value === '') {
                $model->deleteByField(array(
                    'snapshot_id'  => $snapshot_id,
                    'warehouse_id' => $warehouse_id,
                ));
            } elseif ($value === 'skip') {
                $model->saveAuto($snapshot_id, $warehouse_id, array(
                    'mode'   => shopMigratePluginOzonSettings::MODE_MANUAL,
                    'action' => 'skip',
                ));
            } else {
                $stock_model = new shopStockModel();
                $stock = $stock_model->getById((int) $value);
                if (!$stock) {
                    throw new waException(_wp('Stock not found.'));
                }
                $model->saveAuto($snapshot_id, $warehouse_id, array(
                    'mode'          => shopMigratePluginOzonSettings::MODE_MANUAL,
                    'action'        => 'manual',
                    'shop_stock_id' => $stock['id'],
                ));
            }

            $this->response = array('status' => 'ok');
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }
}
