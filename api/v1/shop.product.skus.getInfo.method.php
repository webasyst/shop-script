<?php

class shopProductSkusGetInfoMethod extends waAPIMethod
{
    protected $method = 'GET';

    public function execute()
    {
        $sku_id = $this->get('id', true);
        $skus_model = new shopProductSkusModel();
        $sku = $skus_model->getSku($sku_id);
        if ($sku) {
            $this->response = $sku;
        } else {
            throw new waAPIException('invalid_param', 'SKU not found', 404);
        }
    }
}