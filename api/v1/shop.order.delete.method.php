<?php

class shopOrderDeleteMethod extends shopApiMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $order_id = $this->post('id', true);

        $order_model = new shopOrderModel();
        if (!$order_model->getById($order_id)) {
            throw new waAPIException('invalid_param', _w('Order not found.'), 404);
        }

        $workflow = new shopWorkflow();
        $this->response = $workflow->getActionById('delete')->run($order_id);

    }
}
