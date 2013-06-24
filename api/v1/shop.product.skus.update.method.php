<?php

class shopProductSkusUpdateMethod extends waAPIMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $sku_id = $this->get('id', true);
        $skus_model = new shopProductSkusModel();
        $sku = $skus_model->getById($sku_id);
        if (!$sku) {
            throw new waAPIException('invalid_param', 'SKU not found', 404);
        }

        $this->checkProductRights($sku['product_id']);

        $data = waRequest::post();

        if ($skus_model->update($sku_id, $data)) {
            $method = new shopProductSkusGetInfoMethod();
            $this->response = $method->getResponse(true);
        } else {
            throw new waAPIException('server_error', 500);
        }

    }

    public function checkProductRights($product_id)
    {
        $product_model = new shopProductModel();
        $product = $product_model->getById($product_id);

        if (!$product) {
            throw new waAPIException('invalid_param', 'Product not found', 404);
        }

        if (!$this->getRights('type.all') || !$this->getRights('type.'.$product['type_id'])) {
            throw new waAPIException('access_denied', 500);
        }
        return true;
    }
}