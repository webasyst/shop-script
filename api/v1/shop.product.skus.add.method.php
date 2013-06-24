<?php

class shopProductSkusAddMethod extends shopProductSkusUpdateMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $product_id = $this->get('product_id', true);
        $this->checkProductRights($product_id);
        $data = waRequest::post();

        $skus_model = new shopProductSkusModel();
        if ($sku_id = $skus_model->add($data)) {
            $_GET['id'] = $sku_id;
            $method = new shopProductSkusGetInfoMethod();
            $this->response = $method->getResponse(true);
        } else {
            throw new waAPIException('server_error', 500);
        }
    }
}