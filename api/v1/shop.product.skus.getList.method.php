<?php

class shopProductSkusGetListMethod extends waAPIMethod
{
    protected $method = 'GET';

    public function execute()
    {
        $product_id = $this->get('product_id');
        $product_model = new shopProductModel();
        $product = $product_model->getById($product_id);
        if (!$product) {
            throw new waAPIException('invalid_param', 'Product not found', 404);
        }
        $skus_model = new shopProductSkusModel();
        $skus = $skus_model->getData(new shopProduct($product));
        foreach ($skus as &$sku) {
            $sku['currency'] = $product['currency'];
            $sku['primary_price'] = (double)$sku['primary_price'];
        }
        unset($sku);

        $this->response = array_values($skus);
        $this->response['_element'] = 'sku';
    }
}