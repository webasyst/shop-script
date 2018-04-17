<?php


class shopOrderContactSaveMethod extends shopApiMethod
{
    protected $method = 'POST';

    protected $courier_allowed = true;

    public function execute()
    {
        $order_id = waRequest::post('order_id', null, waRequest::TYPE_INT);
        $contact_data = waRequest::post('contact', array(), 'array');

        if ($this->courier && $this->courier['rights_customer_edit'] == 0) {
            throw new waAPIException('access_denied', 'Access denied to limited courier token.', 403);
        }

        // Check courier access rights
        if ($this->courier) {
            $order_params_model = new shopOrderParamsModel();
            $courier_id = $order_params_model->getOne($order_id, 'courier_id');
            if (empty($courier_id) || ($courier_id != $this->courier['id'])) {
                throw new waAPIException('access_denied', 'Access denied to limited courier token.', 403);
            }
        }

        $order_model = new shopOrderModel();
        $order = $order_model->getById($order_id);
        if (!$order) {
            throw new waAPIException('invalid_param', 'Order not found', 404);
        }

        $contact = new waContact($order['contact_id']);

        foreach ($contact_data as $k => $v) {
            $contact->set($k, $v);
        }

        $save = $contact->save(array(), true);

        if ($save) {
            $this->response = array('errors' => $save);
        } else {
            $this->response = array('status' => 'ok');
        }
    }
}