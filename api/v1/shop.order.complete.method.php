<?php

class shopOrderCompleteMethod extends waAPIMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $order_id = $this->post('id', true);

        $order_model = new shopOrderModel();
        if (!$order_model->getById($order_id)) {
            throw new waAPIException('invalid_param', 'Order not found', 404);
        }

        $workflow = new shopWorkflow();
        $this->response = $workflow->getActionById('complete')->run($order_id);

    }
}