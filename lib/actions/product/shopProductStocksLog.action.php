<?php

class shopProductStocksLogAction extends shopStocksLogListAction
{
    public function execute()
    {
        // get id of product and check existing
        $id = waRequest::get('id', null, waRequest::TYPE_INT);
        $product_model = new shopProductModel();
        $product = $product_model->getById($id);
        if (!$product) {
            throw new waException(_w("Unkown product"));
        }
        $this->product_id = $id;
        parent::execute();
    }
}