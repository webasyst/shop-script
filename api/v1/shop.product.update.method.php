<?php

class shopProductUpdateMethod extends waAPIMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $id = $this->get('id', true);
        $product = $this->getProduct($id);

        $data = waRequest::post();
        if (isset($data['type_id'])) {
            $this->checkRights($data['type_id']);
        }

        $this->checkSku($data);

        $p = new shopProduct($product);
        if ($p->save($data, true, $errors)) {
            $_GET['id'] = $p->getId();
            $method = new shopProductGetInfoMethod();
            $this->response = $method->getResponse(true);
        } else {
            throw new waAPIException('server_error', implode(",\n", $errors), 500);
        }
    }

    protected function getProduct($id)
    {
        $product_model = new shopProductModel();
        $product = $product_model->getById($id);
        if (!$product) {
            throw new waAPIException('invalid_param', 'Product not found', 404);
        }
        $this->checkRights($product['type_id']);
        return $product;
    }

    protected function checkRights($type_id)
    {
        if (!$this->getRights('type.'.$type_id)) {
            throw new waAPIException('access_denied', 403);
        }
    }

    public function checkSku(&$data)
    {
        if (!isset($data['skus'])) {
            return true;
        }
        if (!is_array($data['skus'])) {
            throw new waAPIException('invalid_param', 'Invalid param skus');
        }
        foreach ($data['skus'] as &$sku) {
            if (!$sku || !is_array($sku)) {
                throw new waAPIException('invalid_param', 'Invalid param skus');
            }
            if (isset($sku['virtual'])) {
                unset($sku['virtual']);
            }
        }
        unset($sku);
        return true;
    }
}