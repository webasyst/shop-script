<?php

class shopOrderActionMethod extends waAPIMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $order_id = @(int) $this->post('id', true);
        $action_id = @(string) $this->post('action', true);

        $order_model = new shopOrderModel();
        if (!$order_model->getById($order_id)) {
            throw new waAPIException('invalid_param', 'Order not found', 404);
        }

        try {
            $workflow = new shopWorkflow();
            $action = $workflow->getActionById($action_id);
            if (!$action) {
                throw new waAPIException('invalid_param', 'Action not found', 404);
            }
            $this->response = $action->run($order_id);
        } catch (Exception $e) {
            throw new waAPIException('server_error', $e->getMessage(), 500);
        }
    }
}