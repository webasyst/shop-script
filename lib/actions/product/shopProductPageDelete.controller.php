<?php

class shopProductPageDeleteController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id', null, waRequest::TYPE_INT);
        if (!$id) {
            throw new waException(_w("Unknown page"));
        }

        $product_pages_model = new shopProductPagesModel();
        $page = $product_pages_model->getById($id);
        if (!$page) {
            throw new waException(_w("Unknown page"));
        }

        // check rights
        $product_model = new shopProductModel();
        if (!$product_model->checkRights($page['product_id'])) {
            throw new waException(_w("Access denied"));
        }

        $product_pages_model->delete($id);
    }
}