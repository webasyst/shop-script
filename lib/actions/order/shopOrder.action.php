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
        $id = (int)waRequest::get('id');

        try {
            $_order = new shopOrder($id);
        } catch (waException $ex) {
            if ($ex->getCode() === 404) {
                $id = shopHelper::decodeOrderId($id);
                try {
                    $_order = new shopOrder($id);
                } catch (waException $ex) {
                    //TODO try to use decided order id
                    $this->view->assign('order', false);
                    return;
                }
            } else {
                throw $ex;
            }
        }

        $elements = $_order->workflow_action_elements;


        /**
         * @var shopConfig $config
         */
        $config = $this->getConfig();

        $params = $_order->params;

        // Main customer info - top fields
        $main_contact_info = shopCustomer::getCustomerTopFields($_order->wa_contact);

        list($customer_delivery_date, $customer_delivery_time) = shopHelper::getOrderCustomerDeliveryTime($params);
        list($shipping_date, $shipping_time_start, $shipping_time_end) = shopHelper::getOrderShippingInterval($params);

        $order_model = new shopOrderModel();
        $order_data_array = $_order->dataArray();
        $order_items = $this->extendOrderItems($order_data_array);
        $order_data_array['contact'] = $_order->contact_essentials;
        if (!empty($order_data_array['contact']['name'])) {
            $order_data_array['contact']['name'] = htmlspecialchars($order_data_array['contact']['name']);
        }
        $order_data_array['coupon'] = $_order['coupon'];
        $order_data_array['state'] = $_order['state'];
        $order_data_array['items'] = $order_items;
        if (!empty($order_data_array['coupon'])) {
            $user = wa()->getUser();
            $order_data_array['coupon']['right'] = !!$user->getRights('shop', 'marketing');
        }

        // Only show billing address if enabled in checkout settings
        $billing_address_html = null;
        $settings = wa('shop')->getConfig()->getCheckoutSettings();
        $form_fields = ifset($settings['contactinfo']['fields'], array());
        if (isset($form_fields['address.billing'])) {
            $billing_address_html = $_order->billing_address_html;
        }

        $this->view->assign(array(
            'tracking'                   => $_order->getTracking('backend'),
            'map'                        => $_order->map,
            'shipping_address_html'      => $_order->shipping_address_html,
            'customer'                   => $_order->shop_customer,
            'customer_contact'           => $_order->contact,
            'customer_essentials'        => $_order->contact_essentials,
            'main_contact_info'          => $main_contact_info,
            'similar_contacts'           => $this->getSimilarContacts($_order->contact),
            'currency'                   => $config->getCurrency(),
            'order'                      => $order_data_array,
            'params'                     => $_order->params,
            'log'                        => $_order->log,
            'last_action_datetime'       => $_order->last_action_datetime,
            'bottom_buttons'             => $elements['bottom_buttons'],
            'top_buttons'                => $elements['top_buttons'],
            'actions_html'               => $elements['actions_html'],
            'buttons'                    => $elements['buttons'],
            'sales_channel'              => $this->formatSalesChannel($_order->params),
            'filter_params'              => $this->getParams(),
            'filter_params_str'          => $this->getParams(true),
            'count_new'                  => $order_model->getStateCounters('new'),
            'timeout'                    => $config->getOption('orders_update_list'),
            'printable_docs'             => $_order->printforms,
            'billing_address'            => $billing_address_html,
            'shipping_address'           => $_order->shipping_address_html,
            'shipping_id'                => ifset($params['shipping_id'], '').'.'.ifset($params['shipping_rate_id'], ''),
            'shipping_date'              => $shipping_date,
            'shipping_time_start'        => $shipping_time_start,
            'shipping_time_end'          => $shipping_time_end,
            'shipping_custom_fields'     => $_order->shipping_custom_fields,
            'customer_delivery_date'     => $customer_delivery_date,
            'customer_delivery_time'     => $customer_delivery_time,
            'customer_delivery_date_str' => ifset($params['shipping_params_desired_delivery.date_str']),
            'offset'                     => $order_model->getOffset($_order->id, $this->getParams(), true),
            'courier'                    => $_order->courier,
        ));

        $event_data = $order_data_array;
        $event_data['shop_order'] = $_order;
        if (waRequest::get('printable')) {
            /**
             * UI hook for extending printable order page version in backend.
             * @event backend_order_print
             * @param array $order Order params.
             * @return array[string][string]string $return[%plugin_id%]['info_section'] HTML output.
             */
            $this->view->assign('backend_order_print', wa()->event('backend_order_print', $event_data, array(
                'info_section',
            )));

            $template = $this->getTemplate();
            $this->setTemplate(preg_replace('@(\.html)$@', '.printable$1', $template));
        } else {
            /**
             * Backend order profile page
             * UI hook allow extends order profile page
             * @event backend_order
             * @param array $order
             * @return array[string][string]string $return[%plugin_id%]['title_suffix'] html output
             * @return array[string][string]string $return[%plugin_id%]['action_button'] html output
             * @return array[string][string]string $return[%plugin_id%]['action_link'] html output
             * @return array[string][string]string $return[%plugin_id%]['info_section'] html output
             * @return array[string][string]string $return[%plugin_id%]['aux_info'] html output
             *
             */
            $this->view->assign('backend_order', wa()->event('backend_order', $event_data, array(
                'title_suffix',
                'action_button',
                'action_link',
                'info_section',
                'aux_info',
            )));
        }
    }

    protected function getSimilarContacts($customer_contact)
    {
        if (!$this->getUser()->getRights('shop', 'customers')) {
            return array();
        }

        if ($customer_contact instanceof waContact) {
            $contact_id = $customer_contact->getId();
        } elseif (wa_is_int($customer_contact)) {
            $contact_id = $customer_contact;
        } else {
            $contact_id = 0;
        }

        return shopCustomer::getDuplicateStats($contact_id);
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

    private function extendOrderItems($order)
    {
        $sku_ids = array();
        $stock_ids = array();
        $product_ids = array();
        $service_ids = array();
        $order_items = $order['items'];

        foreach ($order_items as $item) {
            //get product_id and service_id to clear from deleted items
            if ($item['type'] == 'product') {
                $product_ids[] = $item['product_id'];
            } else {
                $service_ids[] = $item['service_id'];
            }

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
        foreach ($order_items as &$item) {
            if (!empty($stocks[$item['stock_id']])) {
                $item['stock'] = $stocks[$item['stock_id']];
            }
        }
        unset($item);

        $skus = $this->getSkus($sku_ids);

        $sku_stocks = $this->getSkuStocks($sku_ids);

        //get existing services/products
        $product_ids = $this->getProducts($product_ids);
        $service_ids = $this->getServices($service_ids);

        foreach ($order_items as &$item) {

            //check whether the item was deleted
            if ($item['type'] == 'product' && empty($product_ids[$item['product_id']])) {
                $item['deleted'] = 1;
            } elseif ($item['type'] == 'service' &&
                (empty($service_ids[$item['service_id']]) || empty($service_ids[$item['service_id']]['variants'][$item['service_variant_id']]))) {
                //check service and service variants
                $item['deleted'] = 1;
            }

            // product and existing sku
            if ($item['type'] === 'product' && isset($skus[$item['sku_id']])) {
                $s = $skus[$item['sku_id']];
                $all_balance_stocks = $s['count'];

                // for that counts that lower than low_count-thresholds show icon

                if (isset($item['stock'])) {
                    if (isset($sku_stocks[$s['id']][$item['stock']['id']])) {
                        $count = $sku_stocks[$s['id']][$item['stock']['id']]['count'];
                        if ($count <= $item['stock']['low_count']) {
                            $item['stock_icon'] = shopHelper::getStockCountIcon($count, $item['stock']['id'], true, $all_balance_stocks);
                        }
                    }
                } elseif ($s['count'] !== null && $s['count'] <= shopStockModel::LOW_DEFAULT) {
                    $item['stock_icon'] = shopHelper::getStockCountIcon($s['count'], null, true);
                }

            }

            $current_product_name = ifset($product_ids, $item['product_id'], 'name', null);
            if (!empty($skus[$item['sku_id']]['name'])) {
                $current_product_name .= ' ('.$skus[$item['sku_id']]['name'].')';
            }
            $item['current_product_name'] = $current_product_name;
        }
        unset($item);

        // Product codes applicable for this order, along with their values if set
        $order_item_codes_model = new shopOrderItemCodesModel();
        $order_items = $order_item_codes_model->extendOrderItems($order_items, $product_ids);

        return $order_items;
    }

    /**
     * Get existing services and service variants
     * @param array $service_ids
     * @return array|null
     */
    public function getServices($service_ids = array())
    {
        if (!$service_ids) {
            return array();
        }
        $ssm = new shopServiceModel();
        $ssvm = new shopServiceVariantsModel();

        $service = $ssm->getByField('id', $service_ids, 'id');
        $service_variants = $ssvm->getByField('service_id', $service_ids, true);

        if ($service_variants) {
            foreach ($service_variants as $variants) {
                $service[$variants['service_id']]['variants'][$variants['id']] = $variants;
            }
        }

        return $service;
    }

    /**
     * Get existing products
     * @param array $product_ids
     * @return array|null
     */
    public function getProducts($product_ids = array())
    {
        if (!$product_ids) {
            return array();
        }
        $spm = new shopProductModel();
        return $spm->getByField('id', $product_ids, 'id');
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
