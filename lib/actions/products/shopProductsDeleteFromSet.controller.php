<?php

class shopProductsDeleteFromSetController extends waJsonController
{
    public function execute()
    {
        $model = new shopSetProductsModel();
        if (waRequest::post('hash', '')) {
            $model->clearSet(waRequest::get('id'));
        } else {
            $model->deleteProducts(
                waRequest::get('id'),
                waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT)
            );
        }
    }
}