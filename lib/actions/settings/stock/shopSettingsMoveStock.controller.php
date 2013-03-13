<?php

class shopSettingsMoveStockController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id', null, waRequest::TYPE_INT);
        $before_id = waRequest::post('before_id', null, waRequest::TYPE_INT);

        if (!$id) {
            $this->errors[] = "Unknown stock";
            return;
        }
        $stock_model = new shopStockModel();
        if (!$stock_model->move($id, $before_id)) {
            $this->errors[] = "Error when move";
        }
    }
}