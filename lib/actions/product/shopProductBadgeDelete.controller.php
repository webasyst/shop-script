<?php

class shopProductBadgeDeleteController extends waJsonController
{
    public function execute()
    {
        $product_model = new shopProductModel();
        $id = waRequest::get('id', null, waRequest::TYPE_INT);
        $product = $product_model->getById($id);
        if (!$product) {
            $this->errors[] = _w("Unknown product");
            return;
        }
        $product_model->updateById($id, array('badge' => null));
    }
}