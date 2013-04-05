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
        if (!$product_model->checkRights($product)) {
            throw new waException(_w("Access denied"));
        }

        $product_model->updateById($id, array('badge' => null));
    }
}