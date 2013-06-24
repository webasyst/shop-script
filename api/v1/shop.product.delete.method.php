<?php

class shopProductDeleteMethod extends waAPIMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $id = $this->post('id', true);
        if (!is_array($id)) {
            if (strpos($id, ',') !== false) {
                $id = array_map('intval', explode(',', $id));
            } else {
                $id = array($id);
            }
        }

        $product_model = new shopProductModel();
        if ($product_model->delete($id)) {
            $this->response = true;
        } else {
            throw new waAPIException('access_denied', 403);
        }
    }
}