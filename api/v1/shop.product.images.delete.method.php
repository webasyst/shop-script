<?php

class shopProductImagesDeleteMethod extends shopProductUpdateMethod
{
    public function execute()
    {
        $id = $this->post('id', true);
        $images_model = new shopProductImagesModel();
        $image = $images_model->getById($id);

        if (!$image) {
            throw new waAPIException('invalid_param', 'Product image not found', 404);
        }

        // check product rights
        $this->getProduct($image['product_id']);

        $product_images_model = new shopProductImagesModel();
        if ($product_images_model->delete($id)) {
            $this->response = true;
        } else {
            throw new waAPIException('server_error', 500);
        }
    }
}