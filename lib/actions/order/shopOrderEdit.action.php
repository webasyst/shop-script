<?php

class shopOrderEditAction extends waViewAction
{
    private $crop_size = null;

    /**
     * @var shopOrderEditorConfig
     */
    protected $order_editor_config;

    /**
     * @var shopOrder
     */
    protected $order;

    /**
     * @var array
     */
    protected $order_data;

    /**
     * @var int|null
     */
    protected $new_order_customer_contact_id;

    public function __construct($params = null)
    {
        parent::__construct($params);

        // init shop order info - after that $this->order, $this->order_data and $this->new_order_customer_contact_id is available
        $this->initShopOrderInfo();
    }

    /**
     * Init shop order info and set it into property ot this action
     *
     * shopOrder $this->order
     * int|null  $this->new_order_customer_contact_id
     *
     * @throws waException
     */
    protected function initShopOrderInfo()
    {
        $order_id = waRequest::get('id', null, waRequest::TYPE_INT);
        if ($order_id) {
            $this->initShopOrderInfoByOrderId($order_id);
        } else {
            $this->initEmptyShopOrderInfo();
        }

        // get initialized by shopOrder backend customer form (shopBackendCustomerForm)
        $form = $this->order->customerForm();

        // By default get first address from contact to fill form address
        $form->setAddressDisplayType('first');

        // But if there is shipping address attached to order, set this specific address to fill form address
        if ($this->order->shipping_address) {
            // not get address from contact to fill form address
            $form->setAddressDisplayType('none');
            // set specific address
            $form->setValue('address.shipping', ['data' => $this->order->shipping_address]);
        }
    }

    protected function initShopOrderInfoByOrderId($order_id)
    {
        $this->new_order_customer_contact_id = null;
        $this->order = new shopOrder($order_id, [
            'customer_form' => new shopBackendCustomerForm()
        ]);
        $this->order_data = $this->getOrderData($this->order);
    }

    protected function initEmptyShopOrderInfo()
    {
        $this->order_data = [];
        $this->new_order_customer_contact_id = waRequest::get('customer_id', null, waRequest::TYPE_INT);
        $this->order = new shopOrder([
            'contact_id' => $this->new_order_customer_contact_id,
        ], [
            'customer_form' => new shopBackendCustomerForm()
        ]);
    }


    public function execute()
    {
        // Make sure edit is allowed
        $workflow = new shopWorkflow();
        $edit_action = $workflow->getActionById('edit');
        if (!$edit_action->isAvailable($this->order)
            || wa()->getUser()->getRights('shop', 'orders') == shopRightConfig::RIGHT_ORDERS_COURIER
        ) {
            throw new waException(_w('Access denied'), 403);
        }

        /**
         * Backend order edit page
         * @event backend_order_edit
         * @param array $order
         * @return array[string][string] $return[%plugin_id%] html output
         */
        $this->view->assign('backend_order_edit', wa()->event('backend_order_edit', $this->order_data));
        $shipping_address = $this->order->shipping_address;
        $shipping_methods = $this->getShipMethods($shipping_address, $this->order_data);


        //Calculate total items discount
        $items_total_discount = null;
        $item_discount = $this->order->items;
        if (!empty($item_discount)) {
            foreach ($item_discount as $item) {
                $items_total_discount += (int)$item['total_discount'];
            }
        }

        $money_on_hold_warning = $this->getMoneyOnHoldWarningType($this->order);

        $order_data_array = array();
        if ($this->order_data) {
            $user = wa()->getUser();
            $order_data_array = $this->order->dataArray();
            $order_data_array['tax'] = shopOrderAction::calculateNotIncludedTax($order_data_array);
            $order_data_array['contact'] = $this->order->contact_essentials;
            $order_data_array['shipping_id'] = $this->order['shipping_id'];
            $order_data_array['items'] = $this->order_data['items'];
            $order_data_array['items_total_discount'] = $items_total_discount;
            $order_data_array['coupon'] = $this->order->coupon;
            if (!empty($order_data_array['coupon'])) {
                $order_data_array['coupon']['right'] = !!$user->getRights('shop', 'marketing');
            }
            if ($money_on_hold_warning) {
                $order_data_array['amount_on_hold'] = $this->order['amount_on_hold'];
            }
        }

        $tax_model = new shopTaxModel();
        $stock_model = new shopStockModel();

        $sales_model = new shopSalesModel();
        $customer_sources = $sales_model->getAllSalesChannels();

        $order_model = new shopOrderModel();

        $customer_form = $this->order->customerForm();

        $order_editor_config = $this->getOrderEditorConfig();

        $units = shopHelper::getUnits();
        $formatted_units = shopFrontendProductAction::formatUnits($units);
        $fractional_config = shopFrac::getFractionalConfig();

        $order_has_frac = false;
        $order_has_units = false;
        if (!empty($order_data_array['items'])) {
            $order_has_frac = shopFrac::itemsHaveFractionalQuantity($order_data_array['items']);
            $order_has_units = shopUnits::itemsHaveCustomStockUnits($order_data_array['items']);
        }

        $payment_methods = shopHelper::getPaymentMethods($order_data_array, $order_has_frac, $order_has_units);

        $this->view->assign(array(
            'order_editor_config'          => $order_editor_config,
            'form'                         => $customer_form,
            'form_namespace'               => $customer_form->opt('namespace'),
            'order_storefront'             => $this->getOrderStorefront($this->order_data),
            'order'                        => $order_data_array,
            'stocks'                       => $stock_model->getAll('id'),
            'currency'                     => $this->order->currency,
            'count_new'                    => $order_model->getStateCounters('new'),
            'taxes_count'                  => $tax_model->countAll(),
            'shipping_address'             => $this->order->shipping_address,
            'has_contacts_rights'          => true,
            'money_on_hold_warning'        => $money_on_hold_warning,
            'customer_validation_disabled' => wa()->getSetting('disable_backend_customer_form_validation'),
            'shipping_methods'             => shopHelper::truncateFieldNameInArray($shipping_methods, 64),
            'ignore_stock_count'           => wa()->getSetting('ignore_stock_count'),
            'storefronts'                  => $this->getStorefronts(),
            'new_order_for_client'         => $this->new_order_customer_contact_id,
            'customer_sources'             => $customer_sources,
            'discount'                     => $this->order->discount,
            'discount_description'         => $this->order->discount_description,
            'items_discount'               => $item_discount,
            'contact'                      => $this->order->contact,
            "formatted_units"              => $formatted_units,
            "fractional_config"            => $fractional_config,
            'payment_methods'              => shopHelper::truncateFieldNameInArray(array_values($payment_methods), 64),
            'noproduct_item_name'          => wa('shop')->getConfig()->getOrderNoproductItemName(),
        ));
    }

    /**
     * @param shopOrder $order
     * @return array
     * @throws waException
     */
    private function getOrderData($order)
    {
        $order_id = $order['id'];
        if (!$order_id) {
            throw new waException(_w('Order not found.'), 404);
        }

        $order_data = $order->dataArray();
        $order_data['contact'] = $order->contact_essentials;
        $order_data['shipping_id'] = $order['shipping_id'];
        $order_data['subtotal'] = $order['subtotal'];
        $order_data['items'] = $order['items_extended'];

        // Gather sku_ids to fetch stocks
        $sku_ids = array();
        foreach ($order_data['items'] as $item) {
            foreach ($item['skus'] as $sku) {
                if (empty($sku['fake'])) {
                    $sku_ids[] = $sku['id'];
                }
            }
        }

        // How many of each SKU are left in stock
        $sku_stocks = array();
        if ($sku_ids) {
            $product_stocks_model = new shopProductStocksModel();
            $sku_stocks = $product_stocks_model->getBySkuId($sku_ids);
        }

        // Weight of each SKU
        $weights = array();
        $feature_model = new shopFeatureModel();
        $f = $feature_model->getByCode('weight');
        if ($f) {
            $product_ids = array_values(waUtils::getFieldValues($order_data['items'], 'id', 'id'));
            $values_model = $feature_model->getValuesModel($f['type']);
            $weights = $values_model->getProductValues($product_ids, $f['id']);
        }

        $show_order_counts = $this->getProductOrderCounts();

        foreach ($order_data['items'] as &$item) {
            $this->workupItems($item, $sku_stocks);
            if (isset($weights['skus'][$item['item']['sku_id']])) {
                $w = $weights['skus'][$item['item']['sku_id']];
            } else {
                $w = isset($weights[$item['id']]) ? $weights[$item['id']] : 0;
            }
            $item['weight'] = $w;

            $item["item"]["quantity"] = floatval($item["item"]["quantity"]);
            $item["show_order_counts"] = $show_order_counts;
        }
        unset($item);

        return $order_data;
    }

    public static function getProductOrderCounts() {
        $result = false;

        $type_model = new shopTypeModel();
        $types = $type_model->getAll();
        foreach ($types as $type) {
            if ($type['order_multiplicity_factor_fixed'] < 2 && $type['order_count_min_fixed'] < 2) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    private function getShipMethods($shipping_address, $order)
    {
        $shipping_id = null;
        if ($order) {
            $order_items = $order['items'];
            $order_currency = $order['currency'];
            $order_total = $order['subtotal'] - $order['discount'];
            if (!empty($order['params']['shipping_id'])) {
                $shipping_id = $order['params']['shipping_id'];
                $allow_external_for = array($shipping_id);
            } else {
                $allow_external_for = array();
            }
        } else {
            $allow_external_for = array();
            $order_items = array();
            $order_currency = null;
            $order_total = 0;
        }

        $params = array(
            'currency'           => $order_currency,
            'total_price'        => $order_total,
            'no_external'        => true,
            'allow_external_for' => $allow_external_for,
            'shipping_params'    => array(),
            'custom_html'        => true,
            'departure_datetime' => shopDepartureDateTimeFacade::getDeparture(null, ifset($order, 'params', 'storefront', null)),
        );

        if ($shipping_id && !empty($order['params'])) {
            foreach ($order['params'] as $name => $value) {
                if (preg_match('@^shipping_params_(.+)$@', $name, $matches)) {
                    if (!isset($params['shipping_params'][$shipping_id])) {
                        $params['shipping_params'][$shipping_id] = array();
                    }
                    $params['shipping_params'][$shipping_id][$matches[1]] = $value;
                }
            }
        }

        if (!empty($order['params']['payment_id'])) {
            try {
                $payment = shopPayment::getPluginInfo($order['params']['payment_id']);
                $params['payment_type'] = array_keys(ifset($payment, 'options', 'payment_type', []));
            } catch (waException $ex) {
                //Plugin not found;
            }
        }

        return shopHelper::getShippingMethods($shipping_address, $order_items, $params);
    }

    private function workupItems(&$item, $sku_stocks)
    {
        $item['quantity'] = $item['item']['quantity'];

        $size = $this->getCropSize();
        if (empty($item['image_id'])) {
            $item['url_crop_small'] = null;
        } else {
            $image = array(
                'id'         => $item['image_id'],
                'filename'   => $item['image_filename'],
                'product_id' => $item['id'],
                'ext'        => $item['ext'],
            );
            $item['url_crop_small'] = shopImage::getUrl($image, $size);
        }

        // aggregated stocks count icon for product
        if (empty($item['fake'])) {
            $item['icon'] = shopHelper::getStockCountIcon($item['count'], null, true);
        }

        foreach ($item['skus'] as &$sku) {
            if (empty($sku['fake'])) {
                // detailed stocks count icon for sku
                if (empty($sku_stocks[$sku['id']])) {
                    $sku['icon'] = shopHelper::getStockCountIcon($sku['count'], null, true);
                } else {
                    $icons = array();
                    $counts_htmls = array();
                    foreach ($sku_stocks[$sku['id']] as $stock_id => $stock) {
                        $icons[$stock_id] = shopHelper::getStockCountIcon($stock['count'], $stock_id, true);
                        if ($stock['count'] === null) {
                            $counts_htmls[$stock_id] = sprintf(str_replace('%d', '%s', _w('%d left')), '∞');
                        } else {
                            $counts_htmls[$stock_id] = _w('%s left', '%s left', shopFrac::discardZeros($stock['count']));
                        }
                    }
                    $sku['icon'] = shopHelper::getStockCountIcon($sku['count'], null, true);
                    $sku['icons'] = $icons;
                    $sku['count_htmls'] = $counts_htmls;
                }
            }
        }
        unset($sku);

        $this->roundingItemsData($item);
    }

    protected function roundingItemsData(&$item)
    {
        if (!isset($item['services'])) {
            return null;
        }

        $out_currency = $this->order->currency;

        if ($item['currency'] != $out_currency) {
            foreach (array('price', 'min_price', 'max_price') as $key) {
                $item[$key] = shopRounding::roundCurrency($item[$key], $out_currency);
            }

            if ($item['min_price'] == $item['max_price']) {
                $item['price_str'] = wa_currency($item['min_price'], $out_currency);
                $item['price_html'] = wa_currency_html($item['min_price'], $out_currency);
            } else {
                $item['price_str'] = wa_currency($item['min_price'], $out_currency).'...'.wa_currency($item['max_price'], $out_currency);
                $item['price_html'] = wa_currency_html($item['min_price'], $out_currency).'...'.wa_currency_html($item['max_price'], $out_currency);
            }

            foreach ($item['skus'] as &$sku) {
                $sku['price'] = shopRounding::roundCurrency($sku['price'], $out_currency);
                $sku['price_str'] = ($sku['price'] >= 0 ? '+' : '-').wa_currency($sku['price'], $out_currency);
                $sku['price_html'] = ($sku['price'] >= 0 ? '+' : '-').wa_currency_html($sku['price'], $out_currency);
            }
        }

        foreach ($item['services'] as $service_id => &$service) {
            $service_currency = $service['currency'];

            foreach ($service['variants'] as &$variant) {
                // Always round off interest and if currency conversion occurs.
                if (($service_currency == '%' || $service_currency != $out_currency) && wa()->getSetting('round_services')) {
                    $variant['price'] = shopRounding::roundCurrency($variant['price'], $out_currency);
                }
                $variant['price_str'] = ($variant['price'] >= 0 ? '+' : '-').wa_currency($variant['price'], $out_currency);
                $variant['price_html'] = ($variant['price'] >= 0 ? '+' : '-').wa_currency_html($variant['price'], $out_currency);
            }
            unset($variant);

            // Sets the default price for the service.
            $default_variant = ifset($service, 'variants', $service['variant_id'], []);
            if (isset($default_variant['price'])) {
                $service['price'] = $default_variant['price'];
                if (isset($default_variant['percent_price'])) {
                    $service['percent_price'] = $default_variant['percent_price'];
                }
            } else {
                // Invalid database state.
                unset($item['services'][$service_id]);
            }
        }

        unset($service);
    }

    public function getOrderStorefront($order)
    {
        $storefront = rtrim((string)ifset($order['params']['storefront'], ''), '/*');
        if (strpos($storefront, '/') !== false) {
            $storefront .= '/';
        }
        return $storefront;
    }

    private function getCropSize()
    {
        if ($this->crop_size === null) {
            $config = $this->getConfig();
            /**
             * @var shopConfig $config
             */
            $this->crop_size = $config->getImageSize('crop_small');
        }
        return $this->crop_size;
    }

    protected function getStorefronts()
    {
        $list = new shopStorefrontList();
        $storefronts = $list->fetchAll(array('contact_type'));
        return $storefronts;
    }

    /**
     * @return shopOrderEditorConfig
     */
    protected function getOrderEditorConfig()
    {
        if (empty($this->order_editor_config)) {
            $this->order_editor_config = new shopOrderEditorConfig();
        }
        return $this->order_editor_config;
    }

    protected function getMoneyOnHoldWarningType($order)
    {
        $order_mode = shopOrderMode::getMode($order);
        if ($order_mode['mode'] == shopOrderMode::MODE_DISABLED) {
            throw new waException(_w('Order not found.'), 404);
        } else if ($order_mode['mode'] != shopOrderMode::MODE_ENABLED) {
            return $order_mode['mode'];
        }
    }
}
