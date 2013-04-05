<?php

class shopProductImageMoveController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id', 0, waRequest::TYPE_INT);
        $before_id = waRequest::post('before_id', null, waRequest::TYPE_INT);

        if (!$id) {
            throw new waException(_w("Unknown image"));
        }

        $product_images_model = new shopProductImagesModel();
        $image = $product_images_model->getById($id);
        if (!$image) {
            throw new waException(_w("Unkown image"));
        }

        $product_model = new shopProductModel();
        if (!$product_model->checkRights($image['product_id'])) {
            throw new waException(_w("Access denied"));
        }

        if (!$product_images_model->move($id, $before_id)) {
            throw new waException(_w("Error when move"));
        }
    }
}