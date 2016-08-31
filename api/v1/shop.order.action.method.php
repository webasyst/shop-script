<?php

class shopOrderActionMethod extends shopApiMethod
{
    protected $method = 'POST';
    protected $courier_allowed = true;

    public function execute()
    {
        $order_id = @(int) $this->post('id', true);
        $action_id = @(string) $this->post('action', true);

        $order_model = new shopOrderModel();
        if (!$order_model->getById($order_id)) {
            if ($this->courier) {
                throw new waAPIException('access_denied', 'Access denied to limited courier token.', 403);
            } else {
                throw new waAPIException('invalid_param', 'Order not found', 404);
            }
        }

        // Check courier access rights
        if ($this->courier) {
            $order_params_model = new shopOrderParamsModel();
            $courier_id = $order_params_model->getOne($order_id, 'courier_id');
            if (empty($courier_id) || ($courier_id != $this->courier['id'])) {
                throw new waAPIException('access_denied', 'Access denied to limited courier token.', 403);
            }
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
