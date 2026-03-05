<?php

class shopMigratePluginBackendOzonTypeMapController extends waJsonController
{
    public function execute()
    {
        try {
            $settings = new shopMigratePluginOzonSettings();
            $snapshot_id = $settings->getCurrentSnapshotId();
            if (!$snapshot_id) {
                throw new waException(_wp('Snapshot is missing.'));
            }

            $description_category_id = waRequest::post('description_category_id', 0, waRequest::TYPE_INT);
            $type_id = waRequest::post('type_id', 0, waRequest::TYPE_INT);
            $shop_type_id = waRequest::post('shop_type_id', '', waRequest::TYPE_STRING_TRIM);

            if (!$description_category_id || !$type_id) {
                throw new waException(_wp('Invalid parameters.'));
            }

            $model = new shopMigratePluginOzonTypeMapModel();
            if ($shop_type_id === '') {
                $model->deleteByField(array(
                    'snapshot_id' => $snapshot_id,
                    'description_category_id' => $description_category_id,
                    'type_id' => $type_id,
                ));
            } else {
                $type_model = new shopTypeModel();
                $type = $type_model->getById((int) $shop_type_id);
                if (!$type) {
                    throw new waException(_wp('Type not found.'));
                }
                $model->saveAuto($snapshot_id, $description_category_id, $type_id, array(
                    'mode'           => shopMigratePluginOzonSettings::MODE_MANUAL,
                    'shop_type_id'   => $type['id'],
                    'shop_type_name' => $type['name'],
                ));
            }

            $this->response = array('status' => 'ok');
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }
}
