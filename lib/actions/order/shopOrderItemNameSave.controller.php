<?php

class shopOrderItemNameSaveController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id', null, waRequest::TYPE_INT);
        $name = waRequest::post('name', '', waRequest::TYPE_STRING_TRIM);

        $order_items_model = new shopOrderItemsModel();
        $item = $order_items_model->getById($id);
        if ($item) {
            if ($item['name'] != $name) {
                $name = mb_substr($name, 0, 255);
                $order_items_model->updateById($id, ['name' => $name]);
                $this->response['name'] = htmlspecialchars($name);
            }
        } else {
            $this->errors[] = [
                'id' => 'name',
                'text' => _w('Saving error'),
            ];
        }
    }
}