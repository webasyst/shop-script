<?php
class shopSettingsFeaturesFeatureSortController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        $model = new shopTypeFeaturesModel();
        $id = waRequest::post('feature_id', 0, waRequest::TYPE_INT);
        $type = waRequest::post('type_id', 0, waRequest::TYPE_INT);
        $after_id = waRequest::post('after_id', 0, waRequest::TYPE_INT);
        $item = array('type_id' => $type, 'feature_id' => $id);
        $after = null;
        if ($after_id) {
            $after = array('feature_id' => $after_id, 'type_id' => $type);
        }
        try {
            $model->move($item, $after, $type);
        } catch (waException $e) {
            $this->setError($e->getMessage());
        }
    }
}
