<?php

class shopProductDeletePageController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id', null, waRequest::TYPE_INT);
        if ($id) {
            $product_pages_model = new shopProductPagesModel();
            $product_pages_model->delete($id);
        }
    }
}