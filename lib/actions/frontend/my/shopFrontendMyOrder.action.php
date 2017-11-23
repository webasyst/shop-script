<?php

/**
 * Single order page in customer account.
 */
class shopFrontendMyOrderAction extends shopFrontendAction
{
    public function execute()
    {
        $encoded_order_id = waRequest::param('id');
        $order_id = shopHelper::decodeOrderId($encoded_order_id);
        if (!$order_id) {
            // fall back to non-encoded id
            $order_id = $encoded_order_id;
            $encoded_order_id = shopHelper::encodeOrderId($order_id);
        }

        // Check that order exists and belongs to this user
        $om = new shopOrderModel();
        $order = $om->getOrder($order_id);
        if (!$order || !$this->isAuth($order)) {
            throw new waException(_w('Order not found'), 404);
        }

        if ($order['paid_date']) {
            foreach ($order['items'] as &$i) {
                if (!empty($i['file_name'])) {
                    $i['download_link'] = wa()->getRouteUrl('/frontend/myOrderDownload',
                        array('id' => $order['id'], 'code' => $order['params']['auth_code'], 'item' => $i['id']), true);
                }
            }
            unset($i);
        }

        $workflow = new shopWorkflow();
        $order_params_model = new shopOrderParamsModel();
        $order['params'] = $order_params_model->get($order['id']);
        $order['id_str'] = $encoded_order_id;
        $order['state'] = $workflow->getStateById($order['state_id']);

        // Order subtotal
        $subtotal = 0;
        foreach ($order['items'] as $item) {
            $subtotal += $item['price']*$item['quantity'];
        }

        // Order comment
        $lm = new shopOrderLogModel();
        $l = $lm->getByField(array(
            'action_id' => 'create',
            'order_id' => $order['id'],
        ));
        $order['comment'] = ifempty($l['text']);
        $order['payment_name'] = ifset($order['params']['payment_name'], '');
        $order['shipping_name'] = ifset($order['params']['shipping_name'], '');

        // Shipping and billing addresses
        $settings = wa('shop')->getConfig()->getCheckoutSettings();
        $form_fields = ifset($settings['contactinfo']['fields'], array());
        $formatter = new waContactAddressSeveralLinesFormatter();

        $shipping_address = shopHelper::getOrderAddress($order['params'], 'shipping');
        if ($shipping_address) {
            $shipping_address = $formatter->format(array('data' => $shipping_address));
            $shipping_address = $shipping_address['value'];
        }

        if (isset($form_fields['address.billing'])) {
            $billing_address = shopHelper::getOrderAddress($order['params'], 'billing');
            $billing_address = $formatter->format(array('data' => $billing_address));
            $billing_address = $billing_address['value'];
        } else {
            $billing_address = null;
        }

        if (wa()->getUser()->getId() == $order['contact_id']) {
            $contact = wa()->getUser();
        } else {
            $contact = new waContact($order['contact_id']);
        }

        $contact_data = new shopCustomer(wa()->getUser()->getId());
        $contact_data = $contact_data->getCustomerData();

        foreach (ifempty($contact_data, array()) as $field_id => $value) {
            if ($field_id !== 'contact_id') {
                $contact[$field_id] = $value;
            }
        }

        $payment = '';
        if (!empty($order['params']['payment_id']) && !$order['paid_date']) {
            try {
                $plugin = shopPayment::getPlugin(null, $order['params']['payment_id']);
                $payment = $plugin->payment(waRequest::post(), shopPayment::getOrderData($order, $plugin), false);
            } catch (waException $ex) {
                $payment = $ex->getMessage();
            }
        }
        $this->view->assign('payment', $payment);

        $tracking = '';
        if (!empty($order['params']['shipping_id']) && !empty($order['params']['tracking_number'])) {
            try {
                $plugin = shopShipping::getPlugin(null, $order['params']['shipping_id']);
                $tracking = $plugin->tracking($order['params']['tracking_number']);
            } catch (waException $ex) {
                $tracking = $ex->getMessage();
            }
        }
        $this->view->assign('tracking', $tracking);

        $courier = array();
        if (!empty($order['params']['courier_id'])) {
            $courier_model = new shopApiCourierModel();
            $courier = $courier_model->getById($order['params']['courier_id']);
            foreach ($courier as $field => $value) {
                if (strpos($field, 'api_') === 0) {
                    unset($courier[$field]);
                }
            }
            if (!empty($courier['contact_id'])) {
                $courier['contact'] = new waContact($courier['contact_id']);
            }
        }
        $this->view->assign('courier', $courier);

        /**
         * @event frontend_my_order
         * @return array[string]string $return[%plugin_id%] html output
         */
        $this->view->assign('frontend_my_order', wa()->event('frontend_my_order', $order));

        $this->view->assign('order', $order);
        $this->view->assign('contact', $contact);
        $this->view->assign('customer', $contact);
        $this->view->assign('shipping_address', $shipping_address);
        $this->view->assign('billing_address', $billing_address);
        $this->view->assign('subtotal', $subtotal);

        // Set up layout and template from theme
        $this->setThemeTemplate('my.order.html');

        $this->view->assign('my_nav_selected', 'orders');
        if (!waRequest::isXMLHttpRequest()) {
            $this->setLayout(new shopFrontendLayout());
            $this->getResponse()->setTitle(_w('Order').' '.$encoded_order_id);
            $this->view->assign('breadcrumbs', self::getBreadcrumbs());
            $this->layout->assign('nofollow', true);
        }
    }

    public static function getBreadcrumbs()
    {
        $result = shopFrontendMyOrdersAction::getBreadcrumbs();
        $result[] = array(
            'name' => _w('My orders'),
            'url' => wa()->getRouteUrl('/frontend/myOrders'),
        );
        return $result;
    }

    protected function isAuth($order)
    {
        return $order['contact_id'] == wa()->getUser()->getId() && $order['state_id'] != 'deleted';
    }
}

