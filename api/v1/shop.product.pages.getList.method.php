<?php

class shopProductPagesGetListMethod extends shopApiMethod
{
    public function execute()
    {
        $product_id = $this->get('product_id', true);
        $product_model = new shopProductModel();
        $product = $product_model->getById($product_id);

        if (!$product) {
            throw new waAPIException('invalid_param', _w('Product not found.'), 404);
        }

        $product_pages_model = new shopProductPagesModel();
        $this->response = $product_pages_model->getByProductId($product_id);
        $this->response['_element'] = 'page';
    }
}
