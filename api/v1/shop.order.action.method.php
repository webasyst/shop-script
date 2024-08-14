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
        $order = $order_model->getById($order_id);
        if (!$order) {
            if ($this->courier) {
                throw new waAPIException('access_denied', 'Access denied to limited courier token.', 403);
            } else {
                throw new waAPIException('invalid_param', _w('Order not found.'), 404);
            }
        }

        if ($this->courier) {
            // Check courier access rights
            $order_params_model = new shopOrderParamsModel();
            $courier_id = $order_params_model->getOne($order_id, 'courier_id');
            if (empty($courier_id) || ($courier_id != $this->courier['id'])) {
                throw new waAPIException('access_denied', 'Access denied to limited courier token.', 403);
            }
        } else {
            // Check normal user access rights
            if (!wa()->getUser()->isAdmin('shop') && !wa()->getUser()->getRights('shop', sprintf('workflow_actions.%s', $action_id))) {
                throw new waAPIException('access_denied', 'Action not available for user', 403);
            }
        }

        try {
            $workflow = new shopWorkflow();
            $actions = $workflow->getStateById($order['state_id'])->getActions($order);

            // Check that action is available for the order
            $action = ifset($actions, $action_id, null);
            if (!$action) {
                throw new waAPIException('invalid_param', _w('Available action not found for the specified order.'), 404);
            }

            $this->response = $action->run($order_id);
        } catch (Exception $e) {
            if ($e instanceof waAPIException) {
                throw $e;
            }
            throw new waAPIException('server_error', $e->getMessage(), 500);
        }
    }
}
