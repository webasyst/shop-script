<?php

class shopStockGetInfoMethod extends waAPIMethod
{
    public function execute()
    {
        $id = $this->get('id', true);

        $stock_model = new shopStockModel();
        $stock = $stock_model->getById($id);

        if (!$stock) {
            throw new waAPIException('invalid_param', 'Stock not found', 404);
        }
        $this->response = $stock;
    }
}