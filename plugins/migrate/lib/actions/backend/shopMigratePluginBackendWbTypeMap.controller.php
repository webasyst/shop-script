<?php

class shopMigratePluginBackendWbTypeMapController extends waJsonController
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
            $subject_id = waRequest::post('subject_id', 0, waRequest::TYPE_INT);
            $parent_id = waRequest::post('parent_id', 0, waRequest::TYPE_INT);
            if (!$subject_id) {
                throw new waException(_wp('Invalid Wildberries type mapping parameters.'));
            }

            $subject = (new shopMigratePluginWbSubjectsModel())->getByField(array(
                'snapshot_id' => $snapshot_id,
                'subject_id'  => $subject_id,
            ));
            if (!$subject) {
                throw new waException(_wp('Wildberries subject was not found in the current snapshot.'));
            }
            if ($parent_id <= 0) {
                $parent_id = (int) $subject['parent_id'];
            }
            if ((int) $subject['parent_id'] !== $parent_id) {
                throw new waException(_wp('Wildberries subject does not belong to the selected parent category.'));
            }

            list($action, $shop_type_id) = $this->getActionAndTarget('shop_type_id');
            $model = new shopMigratePluginWbTypeMapModel();
            $key = array('snapshot_id' => $snapshot_id, 'subject_id' => $subject_id);
            if ($action === 'auto') {
                $model->deleteByField($key);
                $this->response = array('status' => 'ok', 'action' => 'auto');
                return;
            }

            $type = null;
            if ($action === 'map') {
                $type = (new shopTypeModel())->getById($shop_type_id);
                if (!$type) {
                    throw new waException(_wp('Shop-Script product type was not found.'));
                }
            }

            $model->saveMapping($snapshot_id, $parent_id, $subject_id, array(
                'parent_id'      => $parent_id,
                'mode'           => shopMigratePluginWbSettings::MODE_MANUAL,
                'action'         => $action,
                'shop_type_id'   => $type ? (int) $type['id'] : null,
                'shop_type_name' => $type ? (string) $type['name'] : null,
            ));
            $this->response = array('status' => 'ok', 'action' => $action, 'type' => $type);
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
        // Compatibility with pages opened before the forced-create option was
        // removed. Treat the stale value as automatic matching instead of
        // persisting a mapping capable of creating a duplicate type.
        if ($action === 'create') {
            $action = 'auto';
        }
        if (!in_array($action, array('auto', 'skip', 'map'), true)) {
            throw new waException(_wp('Invalid Wildberries type mapping action.'));
        }
        if ($action === 'map' && $target_id <= 0) {
            throw new waException(_wp('Select a Shop-Script product type.'));
        }
        return array($action, $target_id);
    }
}
