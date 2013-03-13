<?php
class shopSettingsFeaturesTypeSortController extends waJsonController
{
    public function execute()
    {
        $model = new shopTypeModel();
        $id = waRequest::post('id', 0, waRequest::TYPE_INT);
        $after_id = waRequest::post('after_id', 0, waRequest::TYPE_INT);
        try {
            $model->move($id, $after_id);
        } catch (waException $e) {
            $this->setError($e->getMessage());
        }
    }
}
