<?php

class shopOrderGetInfoMethod extends waAPIMethod
{
    public function execute()
    {
        $id = $this->get('id', true);

        $order_model = new shopOrderModel();
        $data = $order_model->getOrder($id);

        if (!$data) {
            throw new waAPIException('invalid_param', 'Order not found', 404);
        }

        foreach (array('auth_code', 'auth_pin') as $k) {
            if (!empty($data['params'][$k])) {
                unset($data['params'][$k]);
            }
        }

        $this->response = $data;
    }
}