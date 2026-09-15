<?php

class shopMigratePluginBackendWbCategoryMapController extends waJsonController
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
            $entity_type = waRequest::post('entity_type', 'subject', waRequest::TYPE_STRING_TRIM);
            $wb_id = waRequest::post('wb_id', 0, waRequest::TYPE_INT);
            $parent_id = waRequest::post('parent_id', 0, waRequest::TYPE_INT);
            if ($parent_id <= 0) {
                $parent_id = waRequest::post('wb_parent_id', 0, waRequest::TYPE_INT);
            }
            if (!$wb_id || !in_array($entity_type, array('parent', 'subject'), true)) {
                throw new waException(_wp('Invalid Wildberries category mapping parameters.'));
            }

            $this->validateSourceEntity($snapshot_id, $entity_type, $wb_id, $parent_id);
            list($action, $shop_category_id) = $this->getActionAndTarget('shop_category_id');
            $model = new shopMigratePluginWbCategoryMapModel();
            $key = array('snapshot_id' => $snapshot_id, 'entity_type' => $entity_type, 'wb_id' => $wb_id);
            if ($action === 'auto') {
                $model->deleteByField($key);
                $this->response = array('status' => 'ok', 'action' => 'auto');
                return;
            }

            $category = null;
            if ($action === 'map') {
                $category = (new shopCategoryModel())->getById($shop_category_id);
                if (!$category) {
                    throw new waException(_wp('Shop-Script category was not found.'));
                }
                if ((int) $category['type'] !== shopCategoryModel::TYPE_STATIC) {
                    throw new waException(_wp('Select a static Shop-Script category.'));
                }
            }
            $data = array(
                'mode'             => shopMigratePluginWbSettings::MODE_MANUAL,
                'action'           => $action,
                'shop_category_id' => $category ? (int) $category['id'] : null,
            );
            $model->saveMapping($snapshot_id, $entity_type, $wb_id, $parent_id, $data);
            $this->response = array('status' => 'ok', 'action' => $action, 'category' => $category);
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

    private function validateSourceEntity($snapshot_id, $entity_type, $wb_id, $parent_id)
    {
        if ($entity_type === 'parent') {
            $source = (new shopMigratePluginWbParentsModel())->getByField(array(
                'snapshot_id' => $snapshot_id,
                'parent_id'   => $wb_id,
            ));
            if (!$source) {
                throw new waException(_wp('Wildberries parent category was not found in the current snapshot.'));
            }
        } else {
            $source = (new shopMigratePluginWbSubjectsModel())->getByField(array(
                'snapshot_id' => $snapshot_id,
                'subject_id'  => $wb_id,
            ));
            if (!$source) {
                throw new waException(_wp('Wildberries subject was not found in the current snapshot.'));
            }
            if ((int) $source['parent_id'] !== (int) $parent_id) {
                throw new waException(_wp('Wildberries subject does not belong to the selected parent category.'));
            }
        }
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
        if (!in_array($action, array('auto', 'create', 'skip', 'map'), true)) {
            throw new waException(_wp('Invalid Wildberries category mapping action.'));
        }
        if ($action === 'map' && $target_id <= 0) {
            throw new waException(_wp('Select a Shop-Script category.'));
        }
        return array($action, $target_id);
    }
}
