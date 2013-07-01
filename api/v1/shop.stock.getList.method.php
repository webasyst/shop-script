<?php

class shopStockGetListMethod extends waAPIMethod
{
    public function execute()
    {
        $stock_model = new shopStockModel();
        $this->response = $stock_model->getAll();
        $this->response['_element'] = 'stock';
    }
}