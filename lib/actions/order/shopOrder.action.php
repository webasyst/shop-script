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
        wa()->getRequest();
        $order = $this->getOrder();
        if (!$order) {
            $this->view->assign('order', $order);
            return;
        }

        $workflow = new shopWorkflow();
        $actions = $workflow->getStateById($order['state_id'])->getActions($order);
        $bottom_buttons = $top_buttons = $actions_html = $buttons = array();

        $source = 'backend';
        if (isset($order['params']['storefront'])) {
            if (substr($order['params']['storefront'], -1) === '/') {
                $source = $order['params']['storefront'].'*';
            } else {
                $source = $order['params']['storefront'].'/*';
            }
        }
        $notification_model = new shopNotificationModel();
        $transports = $notification_model->getActionTransportsBySource($source);

        foreach ($actions as $action) {
            /**
             * @var shopWorkflowAction $action
             */
            if ($action->getOption('top') || $action->getOption('position') == 'top') {
                $top_buttons[] = $action->getButton();
            } elseif ($action->getOption('position') == 'bottom') {
                $bottom_buttons[] = $action->getButton();
            } elseif ($action->getOption('head')) {
                $html = $action->getHTML($order['id']);
                if ($html) {
                    $actions_html[] = $html;
                } else {
                    $buttons[] = $action->getButton();
                }
            } else {
                $icons = array();
                if (!empty($transports[$action->getId()]['email'])) {
                    $icons[] = 'ss notification-bw';
                }
                if (!empty($transports[$action->getId()]['sms'])) {
                    $icons[] = 'ss phone-bw';
                }
                if ($icons) {
                    $action->setOption('icon', $icons);
                }
                $buttons[] = $action->getButton();
            }
        }

        /**
         * @var shopConfig $config
         */
        $config = $this->getConfig();

        $last_action_datetime = null;

        $log_model = new shopOrderLogModel();
        $log = $log_model->getLog($order['id']);
        $plugins = null;
        $root_url = wa()->getRootUrl();
        foreach ($log as &$l) {
            if ($l['action_id']) {
                $l['action'] = $workflow->getActionById($l['action_id']);

                if (!empty($l['text']) && ($l['action_id'] == 'callback') && strpos($l['text'], ' ')) {
                    $type = 'payment';
                    $chunks = explode(' ', $l['text'], 2);
                    $l['plugin'] = ifset($chunks[0]);
                    $l['text'] = $chunks[1];
                    if ($l['plugin']) {
                        if (preg_match('@^(shop|payment|shipping):(\w+)$@', $l['plugin'], $matches)) {
                            $type = $matches[1];
                            $l['plugin'] = $matches[2];
                        }
                        $info = array();
                        switch ($type) {
                            case 'payment':
                                $info = shopPayment::getPluginInfo($l['plugin']);
                                break;
                            case 'shipping':
                                $info = shopShipping::getPluginInfo($l['plugin']);
                                break;
                            case 'shop':
                                if ($plugins === null) {
                                    $plugins = $this->getConfig()->getPlugins();
                                }
                                $info = ifset($plugins[$l['plugin']]);
                                break;
                        }

                        $l['plugin'] = ifset($info['name'], $l['plugin']);
                        $l['plugin_icon_url'] = ifset($info['icon'][16], ifset($info['img']));
                        if (($root_url !== '/')
                            && !empty($l['plugin_icon_url'])
                            && (strpos($l['plugin_icon_url'], $root_url) !== 0)
                        ) {
                            $l['plugin_icon_url'] = $root_url.$l['plugin_icon_url'];
                        }

                    }
                }

            }
            if ($order['state_id'] == $l['after_state_id']) {
                $last_action_datetime = $l['datetime'];
            }
        }

        $params = $order['params'];
        $tracking = '';
        if (!empty($params['shipping_id'])) {
            try {
                $plugin = shopShipping::getPlugin(null, $params['shipping_id']);
                if (!empty($params['tracking_number'])) {
                    if ($plugin->getProperties('external_tracking')) {
                        $id = sprintf('shop_tracking_%s', $order['id']);
                        $tracking = <<<HTML
<i class="icon16 loading" id="{$id}"></i>
<script type="text/javascript">
(function () {
    $.get('?module=order&action=tracking&order_id={$order['id']}',function(data){
        if(data && data.status=='ok'){
            $('#{$id}').replaceWith(data.data.tracking);
        }
    });
})();
</script>
HTML;

                    } else {
                        $tracking = $plugin->tracking($params['tracking_number']);
                    }
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

        if (!empty($params['coupon_id'])) {
            $coupon_model = new shopCouponModel();
            $order['coupon'] = $coupon_model->getById($params['coupon_id']);
        }

        $settings = $config->getCheckoutSettings();
        $form_fields = ifset($settings['contactinfo']['fields'], array());
        try {
            $map = wa()->getMap()->getHTML(shopHelper::getShippingAddressText($params), array(
                'width'  => '200px',
                'height' => '200px',
                'zoom'   => 13,
                'static' => true,
            ));
        } catch (waException $e) {
            $map = '';
        }
        $this->view->assign('map', $map);

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
            $v = $customer_contact->get($f, 'top,html');
            if ($v) {
                $main_contact_info[] = array(
                    'id'    => $f,
                    'name'  => waContactFields::get($f)->getName(),
                    'value' => is_array($v) ? implode(', ', $v) : $v,
                );
            }
        }

        if ($this->getUser()->getRights('contacts', 'backend')) {
            $similar_contacts = array();
            // by email
            $v = $customer_contact->get('email', 'default');
            $contact_emails_model = new waContactEmailsModel();
            $sql = 'SELECT count(*) FROM '.$contact_emails_model->getTableName().' e
                    JOIN shop_customer c ON e.contact_id = c.contact_id
                    WHERE e.contact_id != i:0 AND e.email = s:1';
            $similar_contacts['email'] = array(
                'value' => $v,
                'count' => $contact_emails_model->query($sql, $order['contact_id'], $v)->fetchField()
            );
            // by phone
            $v = $customer_contact->get('phone', 'default');
            $contact_data_model = new waContactDataModel();
            $sql = 'SELECT count(*) FROM '.$contact_data_model->getTableName()." d
                    JOIN shop_customer c ON d.contact_id = c.contact_id
                    WHERE d.contact_id != i:0 AND d.field = 'phone' AND d.value = s:1";
            $similar_contacts['phone'] = array(
                'value' => $v,
                'count' => $contact_emails_model->query($sql, $order['contact_id'], $v)->fetchField()
            );
        } else {
            $similar_contacts = array();
        }

        $courier = null;
        if (!empty($params['courier_id'])) {
            $courier_model = new shopApiCourierModel();
            $courier = $courier_model->getById($params['courier_id']);
        }

        if (!empty($order['params']['storefront'])) {
            $idna = new waIdna();
            $order['params']['storefront_decoded'] = $idna->decode($order['params']['storefront']);
        }

        list($customer_delivery_date, $customer_delivery_time) = shopHelper::getOrderCustomerDeliveryTime($params);
        list($shipping_date, $shipping_time_start, $shipping_time_end) = shopHelper::getOrderShippingInterval($params);

        $this->view->assign(array(
            'customer'             => $customer,
            'customer_contact'     => $customer_contact,
            'main_contact_info'    => $main_contact_info,
            'similar_contacts'     => $similar_contacts,
            'currency'             => $config->getCurrency(),
            'order'                => $order,
            'params'               => $params,
            'log'                  => $log,
            'last_action_datetime' => $last_action_datetime,
            'bottom_buttons'       => $bottom_buttons,
            'top_buttons'          => $top_buttons,
            'actions_html'         => $actions_html,
            'buttons'              => $buttons,
            'sales_channel'        => $this->formatSalesChannel($params),
            'filter_params'        => $this->getParams(),
            'filter_params_str'    => $this->getParams(true),
            'count_new'            => $this->getModel()->getStateCounters('new'),
            'timeout'              => $config->getOption('orders_update_list'),
            'printable_docs'       => shopPrintforms::getOrderPrintforms(array_merge($order, array('params' => $params))),
            'billing_address'      => $billing_address,
            'shipping_address'     => $shipping_address,
            'shipping_id'          => ifset($params['shipping_id'], '').'.'.ifset($params['shipping_rate_id'], ''),
            'shipping_date'        => $shipping_date,
            'shipping_time_start'  => $shipping_time_start,
            'shipping_time_end'    => $shipping_time_end,
            'customer_delivery_date' => $customer_delivery_date,
            'customer_delivery_time' => $customer_delivery_time,
            'customer_delivery_date_str' => ifset($params['shipping_params_desired_delivery.date_str']),
            'offset'               => $this->getModel()->getOffset($order['id'], $this->getParams(), true),
            'courier'              => $courier,
        ));

        /**
         * Backend order profile page
         * UI hook allow extends order profile page
         * @event backend_order
         * @param array $order
         * @return array[string][string]string $return[%plugin_id%]['title_suffix'] html output
         * @return array[string][string]string $return[%plugin_id%]['action_button'] html output
         * @return array[string][string]string $return[%plugin_id%]['action_link'] html output
         * @return array[string][string]string $return[%plugin_id%]['info_section'] html output
         */
        $this->view->assign('backend_order', wa()->event('backend_order', $order, array(
            'title_suffix',
            'action_button',
            'action_link',
            'info_section'
        )));
    }

    protected function formatSalesChannel($params)
    {
        if (empty($params['sales_channel']) || $params['sales_channel'] == 'other:') {
            return _w('Unknown channel');
        } elseif ($params['sales_channel'] == 'backend:') {
            return _w('Backend');
        } elseif ($params['sales_channel'] == 'buy_button:') {
            return _w('Buy button');
        } elseif (substr($params['sales_channel'], 0, 11) == 'storefront:') {
            return _w('Storefront');
        } else {
            $result = array(
                $params['sales_channel'] => $params['sales_channel'],
            );
            wa('shop')->event('backend_reports_channels', $result);
            return ifempty($result[$params['sales_channel']], $params['sales_channel']);
        }
    }

    public function getOrder()
    {
        $id = (int)waRequest::get('id');
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
            $params_str .= '&'.$p.'='.(is_array($v) ? implode('|', $v) : $v);
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

        $sku_ids = array();
        $stock_ids = array();
        foreach ($order['items'] as $item) {
            if ($item['stock_id']) {
                $stock_ids[] = $item['stock_id'];
            }
            if ($item['sku_id']) {
                $sku_ids[] = $item['sku_id'];
            }
        }
        $sku_ids = array_unique($sku_ids);
        $stock_ids = array_unique($stock_ids);

        // extend items by stocks
        $stocks = $this->getStocks($stock_ids);
        foreach ($order['items'] as &$item) {
            if (!empty($stocks[$item['stock_id']])) {
                $item['stock'] = $stocks[$item['stock_id']];
            }
        }
        unset($item);

        $skus = $this->getSkus($sku_ids);
        $sku_stocks = $this->getSkuStocks($sku_ids);

        foreach ($order['items'] as &$item) {
            // product and existing sku
            if (isset($skus[$item['sku_id']])) {
                $s = $skus[$item['sku_id']];

                // for that counts that lower than low_count-thresholds show icon

                if ($s['count'] !== null) {
                    if (isset($item['stock'])) {
                        if (isset($sku_stocks[$s['id']][$item['stock']['id']])) {
                            $count = $sku_stocks[$s['id']][$item['stock']['id']]['count'];
                            if ($count <= $item['stock']['low_count']) {
                                $item['stock_icon'] = shopHelper::getStockCountIcon($count, $item['stock']['id'], true);
                            }
                        }
                    } elseif ($s['count'] <= shopStockModel::LOW_DEFAULT) {
                        $item['stock_icon'] = shopHelper::getStockCountIcon($s['count'], null, true);
                    }
                }
            }
        }
        unset($item);

        return $order;

    }

    public function getSkus($sku_ids)
    {
        if (!$sku_ids) {
            return array();
        }
        $model = new shopProductSkusModel();
        return $model->getByField('id', $sku_ids, 'id');
    }

    public function getStocks($stock_ids)
    {
        if (!$stock_ids) {
            return array();
        }
        $model = new shopStockModel();
        return $model->getById($stock_ids);
    }

    public function getSkuStocks($sku_ids)
    {
        if (!$sku_ids) {
            return array();
        }
        $model = new shopProductStocksModel();
        return $model->getBySkuId($sku_ids);
    }
}
