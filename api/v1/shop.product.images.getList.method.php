<?php

class shopProductImagesGetListMethod extends waAPIMethod
{
    public function execute()
    {
        $product_id = $this->get('product_id', true);
        $product_model = new shopProductModel();
        $product = $product_model->getById($product_id);
        if (!$product) {
            throw new waAPIException('invalid_param', 'Product not found', 404);
        }

        $p = new shopProduct($product);
        $this->response = array_values($p->getImages('thumb', true));
        $this->response['_element'] = 'image';
    }
}
