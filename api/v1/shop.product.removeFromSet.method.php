<?php

class shopProductRemoveFromSetMethod extends shopProductUpdateMethod
{
    public function execute()
    {
        $id = $this->get('id', true);
        $this->getProduct($id);

        $set_id = $this->post('set_id', true);
        $set_model = new shopSetModel();
        $set = $set_model->getById($set_id);

        if (!$set) {
            throw new waAPIException('invalid_param', 'Set not found', 404);
        }

        if ($set['type'] == shopSetModel::TYPE_DYNAMIC) {
            throw new waAPIException('invalid_param', 'Set type must be static');
        }

        $set_products_model = new shopSetProductsModel();
        $this->response = $set_products_model->deleteProducts($set_id, $id);
    }
}