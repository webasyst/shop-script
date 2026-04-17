<?php

class shopOrderAssignContactController extends waJsonController
{
    public function execute()
    {
        $order_id = waRequest::post('order_id', null, waRequest::TYPE_INT);
        $assigned_contact_id = waRequest::post('assigned_contact_id', null, waRequest::TYPE_INT);

        if (wa()->getUser()->getRights('shop', 'orders') < shopRightConfig::RIGHT_ORDERS_FULL) {
            throw new waException(_w('Access denied'), 403);
        }
        $this->response = false;
        $order_model = new shopOrderModel();
        $order = $order_model->getOrder($order_id);
        $order_params_model = new shopOrderParamsModel();

        if (!$assigned_contact_id) {
            $update = ['assigned_contact_id' => null];
        } else {
            $assigned_contact = new waContact($assigned_contact_id);
            if ($assigned_contact->exists() && $order) {
                $update = ['assigned_contact_id' => $assigned_contact_id];
                $assigned_user_right = (new waContact($assigned_contact_id))->getRights('shop', 'orders');
                switch ($assigned_user_right) {
                    case shopRightConfig::RIGHT_ORDERS_COURIER:
                        $text = _w('The order was assigned to courier').': '.htmlspecialchars(ifempty($assigned_contact, 'name', '('.$assigned_contact_id.')'));
                        $param = ['courier_contact_id' => $assigned_contact_id];
                        $update['courier_contact_id'] = $assigned_contact_id;
                        break;
                    case shopRightConfig::RIGHT_ORDERS_FULFILLMENT:
                        $text = _w('The order was assigned to responsible for fulfillment').': '.htmlspecialchars(ifempty($assigned_contact, 'name', '('.$assigned_contact_id.')'));
                        $param = ['fulfillment_contact_id' => $assigned_contact_id];
                        $update['fulfillment_contact_id'] = $assigned_contact_id;
                        break;
                    case shopRightConfig::RIGHT_ORDERS_CASHIER:
                        $text = _w('The order was assigned to cashier').': '.htmlspecialchars(ifempty($assigned_contact, 'name', '('.$assigned_contact_id.')'));
                        $param = ['cashier_contact_id' => $assigned_contact_id];
                        $update['cashier_contact_id'] = $assigned_contact_id;
                        break;
                    default:
                        $text = _w('The order was assigned to manager').': '.htmlspecialchars(ifempty($assigned_contact, 'name', '('.$assigned_contact_id.')'));
                        $param = ['manager_contact_id' => $assigned_contact_id];
                        $update['manager_contact_id'] = $assigned_contact_id;
                }
            }
        }

        if (!empty($text)) {
            $log_model = new shopOrderLogModel();
            $log_model->add([
                'order_id'        => $order_id,
                'action_id'       => 'edit',
                'before_state_id' => $order['state_id'],
                'after_state_id'  => $order['state_id'],
                'text'            => $text,
            ]);
        }

        $this->response = true;
        if (!empty($update)) {
            $this->response = $order_model->updateById($order_id, $update);
            $this->response = $this->response && $order_params_model->set($order_id, $param, false);
        }
    }
}
