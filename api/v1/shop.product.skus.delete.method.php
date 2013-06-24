<?php

class shopProductSkusDeleteMethod extends shopProductSkusUpdateMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $sku_id = $this->post('id', true);
        $skus_model = new shopProductSkusModel();
        // check if sku exists
        $sku = $skus_model->getById($sku_id);
        if (!$sku) {
            throw new waAPIException('invalid_param', 'SKU not found', 404);
        }
        // check access rights
        $this->checkProductRights($sku['product_id']);
        // delete sku
        if ($skus_model->delete($sku_id)) {
            $this->response = true;
        } else {
            throw new waAPIException('server_error', 500);
        }
    }
}