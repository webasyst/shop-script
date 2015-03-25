<?php

class shopProductSkusAddMethod extends shopProductSkusUpdateMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $product_id = $this->get('product_id', true);
        $this->checkProductRights($product_id);
        $data = waRequest::post();
        if (!$data) {
            throw new waAPIException('invalid_param', 'Required data is missing', 400);
        }
        $data['product_id'] = $product_id;

        $skus_model = new shopProductSkusModel();
        if ($sku = $skus_model->add($data)) {
            $_GET['id'] = $sku['id'];
            $method = new shopProductSkusGetInfoMethod();
            $this->response = $method->getResponse(true);
        } else {
            throw new waAPIException('server_error', 500);
        }
    }
}