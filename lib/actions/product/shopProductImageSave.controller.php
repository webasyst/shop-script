<?php

class shopProductImageSaveController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id', 0, waRequest::TYPE_INT);
        if (!$id) {
            throw new waException(_w("Unknown image"));
        }

        $product_images_model = new shopProductImagesModel();
        $image = $product_images_model->getById($id);
        if (!$image) {
            throw new waException(_w("Unknown image"));
        }

        // check rights
        $product_model = new shopProductModel();
        if (!$product_model->checkRights($image['product_id'])) {
            throw new waException(_w("Access denied"));
        }

        $product_images_model->updateById($id, $this->getData());

    }

    public function getData()
    {
        $data = waRequest::post('data', array());
        if (isset($data['id'])) {
            unset($data['id']);
        }
        if (isset($data['edit_datetime'])) {
            unset($data['edit_datetime']);
        }
        if (isset($data['upload_datetime'])) {
            unset($data['upload_datetime']);
        }
        return $data;
    }
}