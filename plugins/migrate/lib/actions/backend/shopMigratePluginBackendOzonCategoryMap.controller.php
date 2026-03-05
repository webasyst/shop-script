<?php

class shopMigratePluginBackendOzonCategoryMapController extends waJsonController
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
            $value = waRequest::post('value', '', waRequest::TYPE_STRING_TRIM);

            if (!$description_category_id) {
                throw new waException(_wp('Invalid parameters.'));
            }

            $model = new shopMigratePluginOzonCategoryMapModel();
            if ($value === '') {
                $model->deleteByField(array(
                    'snapshot_id' => $snapshot_id,
                    'description_category_id' => $description_category_id,
                ));
            } elseif ($value === 'skip') {
                $model->saveAuto($snapshot_id, $description_category_id, array(
                    'mode' => shopMigratePluginOzonSettings::MODE_MANUAL,
                    'action' => 'skip',
                ));
            } else {
                $category_model = new shopCategoryModel();
                $category = $category_model->getById((int) $value);
                if (!$category) {
                    throw new waException(_wp('Category not found.'));
                }
                $model->saveAuto($snapshot_id, $description_category_id, array(
                    'mode'             => shopMigratePluginOzonSettings::MODE_MANUAL,
                    'action'           => 'manual',
                    'shop_category_id' => $category['id'],
                ));
            }

            $this->response = array('status' => 'ok');
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }
}
