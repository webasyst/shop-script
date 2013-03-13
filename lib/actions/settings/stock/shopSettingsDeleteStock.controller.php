<?php

class shopSettingsDeleteStockController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id', null, waRequest::TYPE_INT);
        if ($id) {
            $model = new shopStockModel();
            $model->deleteById($id);
        }
    }
}