<?php

class shopOrderAction extends waViewAction
{
    /**
     * Params of order-list context of order
     * @var array|null
     */
    private $filter_params;
    /**
     * @var shopOrderModel
     */
    private $model;

    public function execute()
    {
        $order = $this->getOrder();
        if (!$order) {
            $this->view->assign('order', $order);
            return;
        }

        $workflow = new shopWorkflow();
        $actions = $workflow->getStateById($order['state_id'])->getActions();
        $bottom_buttons = $top_buttons = $buttons = array();
        foreach ($actions as $action) {
            /**
             * @var shopWorkflowAction $action
             */
            if ($action->getOption('top') || $action->getOption('position') == 'top') {
                $top_buttons[] = $action->getButton();
            } elseif ($action->getOption('position') == 'bottom') {
                $bottom_buttons[] = $action->getButton();
            } else {
                $buttons[] = $action->getButton();
            }
        }

        $config = $this->getConfig();

        $last_action_datetime = null;

        $log_model = new shopOrderLogModel();
        $log = $log_model->getLog($order['id']);
        foreach ($log as &$l) {
            if ($l['action_id']) {
                $l['action'] = $workflow->getActionById($l['action_id']);
            }
            if ($order['state_id'] == $l['after_state_id']) {
                $last_action_datetime = $l['datetime'];
            }
        }

        $order_params_model = new shopOrderParamsModel();
        $params = $order_params_model->get($order['id']);

        $tracking = '';
        if (!empty($params['shipping_id'])) {
            try {
                $plugin = shopShipping::getPlugin(null, $params['shipping_id']);
                if (!empty($params['tracking_number'])) {
                    $tracking = $plugin->tracking($params['tracking_number']);
                }
                if ($custom_fields = $plugin->customFields(new waOrder())) {
                    foreach ($custom_fields as $k => $v) {
                        if (!empty($params['shipping_params_'.$k])) {
                            $custom_fields[$k]['value'] = $params['shipping_params_'.$k];
                        } else {
                            unset($custom_fields[$k]);
                        }
                    }
                    $this->view->assign('custom_fields', $custom_fields);
                }
            } catch (waException $ex) {
                $tracking = $ex->getMessage();
            }
        }
        $this->view->assign('tracking', $tracking);


        //$settings = wa('shop')->getConfig()->getCheckoutSettings();
        //$form_fields = ifset($settings['contactinfo']['fields'], array());

        $formatter = new waContactAddressSeveralLinesFormatter();
        $shipping_address = shopHelper::getOrderAddress($params, 'shipping');
        $this->view->assign('shipping_address_text', shopHelper::getShippingAddressText($params));
        $shipping_address = $formatter->format(array('data' => $shipping_address));
        $shipping_address = $shipping_address['value'];

        if (isset($form_fields['address.billing'])) {
            $billing_address = shopHelper::getOrderAddress($params, 'billing');
            $billing_address = $formatter->format(array('data' => $billing_address));
            $billing_address = $billing_address['value'];
        } else {
            $billing_address = null;
        }

        $customer_model = new shopCustomerModel();
        $customer = $customer_model->getById($order['contact_id']);
        $customer_contact = new waContact($order['contact_id']);

        // Customer info
        $main_contact_info = array();
        foreach (array('email', 'phone', 'im') as $f) {
            if ( ( $v = $customer_contact->get($f, 'top,html'))) {
                $main_contact_info[] = array(
                    'id' => $f,
                    'name' => waContactFields::get($f)->getName(),
                    'value' => is_array($v) ? implode(', ', $v) : $v,
                );
            }
        }

        $this->view->assign(array(
            'customer'          => $customer,
            'customer_contact'  => $customer_contact,
            'main_contact_info' => $main_contact_info,
            'currency'          => $config->getCurrency(),
            'order'             => $order,
            'params'            => $params,
            'log'               => $log,
            'last_action_datetime' => $last_action_datetime,
            'bottom_buttons'    => $bottom_buttons,
            'top_buttons'       => $top_buttons,
            'buttons'           => $buttons,
            'filter_params'     => $this->getParams(),
            'filter_params_str' => $this->getParams(true),
            'count_new'         => $this->getModel()->getStateCounters('new'),
            'timeout'           => $config->getOption('orders_update_list'),
            'printable_docs'    => shopHelper::getPrintForms(array_merge($order, array('params' => $params))),
            'billing_address'   => $billing_address,
            'shipping_address'  => $shipping_address,
            'shipping_id'       => ifset($params['shipping_id'], '').'.'.ifset($params['shipping_rate_id'], ''),
            'offset'            => $this->getModel()->getOffset($order['id'], $this->getParams(), true)
        ));

        /**
         * Backend order profile page
         * UI hook allow extends order profile page
         * @event backend_order
         * @param array $order
         * @return array[string][string]string $return[%plugin_id%]['title_suffix'] html output
         * @return array[string][string]string $return[%plugin_id%]['action_button'] html output
         * @return array[string][string]string $return[%plugin_id%]['action_link'] html output
         */
        $this->view->assign('backend_order', wa()->event('backend_order', $order));
    }

    public function getOrder()
    {
        $id = waRequest::get('id');
        if (!$id) {
            return array();
        }
        $order = $this->_getOrder($id);
        if (!$order) {
            $id = shopHelper::decodeOrderId($id);
            $order = $this->_getOrder($id);
            if (!$order) {
                return array();
            }
        }
        return $order;
    }

    public function getParams($str = false)
    {
        if ($this->filter_params === null) {
            $params = array();
            $state_id = waRequest::get('state_id', null);
            if ($state_id) {
                if (strstr($state_id, '|') !== false) {
                    $params['state_id'] = explode('|', $state_id);
                } else {
                    $params['state_id'] = $state_id;
                }
            }
            $contact_id = waRequest::get('contact_id', null, waRequest::TYPE_INT);
            if ($contact_id) {
                $params['contact_id'] = $contact_id;
            }
            $this->filter_params = $params;
        }
        if (!$str) {
            return $this->filter_params;
        }
        $params_str = '';
        foreach ($this->filter_params as $p => $v) {
            $params_str .= '&'.$p.'='. (is_array($v) ? implode('|', $v) : $v);
        }
        return substr($params_str, 1);
    }

    /**
     * @return shopOrderModel
     */
    public function getModel()
    {
        if ($this->model === null) {
            $this->model = new shopOrderModel();
        }
        return $this->model;
    }

    private function _getOrder($id)
    {
        $order = $this->getModel()->getOrder($id);
        if (!$order) {
            return false;
        }
        $workflow = new shopWorkflow();
        $order['state'] = $workflow->getStateById($order['state_id']);
        $order = shopHelper::workupOrders($order, true);

        // extend items by stocks
        $stock_ids = array();
        foreach ($order['items'] as $item) {
            if ($item['stock_id']) {
                $stock_ids[] = $item['stock_id'];
            }
        }
        $stocks = $this->getStocks($stock_ids);
        foreach ($order['items'] as &$item) {
            if (!empty($stocks[$item['stock_id']])) {
                $item['stock'] = $stocks[$item['stock_id']];
            }
        }
        unset($item);

        return $order;

    }

    public function getStocks($stock_ids)
    {
        if (!$stock_ids) {
            return array();
        }
        $model = new shopStockModel();
        return $model->getById($stock_ids, 'id');
    }
}
