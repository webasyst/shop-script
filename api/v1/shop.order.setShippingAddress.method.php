<?php

class shopOrderSetShippingAddressMethod extends shopApiMethod
{
    protected $method = 'POST';
    protected $courier_allowed = true;

    public function execute()
    {
        $order_id = @(int) $this->post('id', true);

        // Get order data from DB
        $order_model = new shopOrderModel();
        $order = $order_model->getById($order_id);
        if (!$order) {
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

        // Check if address field exists
        if (!waContactFields::get('address')) {
            throw new waAPIException('access_denied', 'Address field is disabled.', 403);
        }

        // Check if order state allows an Edit action
        $workflow = new shopWorkflow();
        $actions = $workflow->getStateById($order['state_id'])->getActions($order);
        if (empty($actions['edit'])) {
            throw new waAPIException('access_denied', 'Edit action is not allowed.', 403);
        }

        // Get address data from POST
        $address_subfields = waContactFields::get('address')->getFields();
        $data = waRequest::post('address', array(), 'array');
        $data = array_intersect_key($data, $address_subfields);
        $data += array_fill_keys(array_keys($address_subfields), null);

        // Prepare order params
        $params = array();
        foreach($data as $sf_id => $value) {
            $value = (string) $value;
            $params['shipping_address.'.$sf_id] = $value;
        }

        try {
            // Update params
            $params_model = new shopOrderParamsModel();
            $params_model->set($order_id, $params, false);

            // Write to order log
            $actions['edit']->postExecute($order, array(
                'text' => _w('Shipping address updated'),
            ));
        } catch (Exception $e) {
            throw new waAPIException('server_error', $e->getMessage(), 500);
        }

        $this->response = 'ok';
    }
}
