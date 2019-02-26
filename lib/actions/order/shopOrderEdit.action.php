<?php

class shopOrderEditAction extends waViewAction
{
    private $crop_size = null;
    /**
     * @var shopOrderModel
     */
    private $order_model;

    public function __construct($params = null)
    {
        $this->order_model = new shopOrderModel();
        return parent::__construct($params);
    }

    public function execute()
    {
        $order_id = waRequest::get('id', null, waRequest::TYPE_INT);

        if ($order_id) {
            $new_order_customer_contact_id = null;
            $order = new shopOrder($order_id);
            $order_data = $this->getOrderData($order);
        } else {
            $order_data = array();
            $new_order_customer_contact_id = waRequest::get('customer_id', null, waRequest::TYPE_INT);
            $order = new shopOrder(array(
                'contact_id' => $new_order_customer_contact_id,
            ));
        }

        /**
         * Backend order edit page
         * @event backend_order_edit
         * @param array $order
         * @return array[string][string] $return[%plugin_id%] html output
         */
        $this->view->assign('backend_order_edit', wa()->event('backend_order_edit', $order_data));
        $shipping_address = $order->shipping_address;
        $shipping_methods = $this->getShipMethods($shipping_address, $order_data);


        //Calculate total items discount
        $items_total_discount = null;
        $item_discount = $order->items;
        if (!empty($item_discount)) {
            foreach ($item_discount as $item) {
                $items_total_discount += (int)$item['total_discount'];
            }
        }

        $order_data_array = array();
        if ($order_data) {
            $order_data_array = $order->dataArray();
            $order_data_array['contact'] = $order->contact_essentials;
            $order_data_array['shipping_id'] = $order['shipping_id'];
            $order_data_array['items'] = $order_data['items'];
            $order_data_array['items_total_discount'] = $items_total_discount;
        }

        $tax_model = new shopTaxModel();
        $stock_model = new shopStockModel();

        $sales_model = new shopSalesModel();
        $customer_sources = $sales_model->getAllSalesChannels();

        $this->view->assign(array(
            'form'                         => $order->customerForm(),
            'order_storefront'             => $this->getOrderStorefront($order_data),
            'order'                        => $order_data_array,
            'stocks'                       => $stock_model->getAll('id'),
            'currency'                     => $order->currency,
            'count_new'                    => $this->order_model->getStateCounters('new'),
            'taxes_count'                  => $tax_model->countAll(),
            'shipping_address'             => $order->shipping_address,
            'has_contacts_rights'          => true,
            'customer_validation_disabled' => wa()->getSetting('disable_backend_customer_form_validation'),
            'shipping_methods'             => $shipping_methods,
            'ignore_stock_count'           => wa()->getSetting('ignore_stock_count'),
            'storefronts'                  => shopHelper::getStorefronts(true),
            'new_order_for_client'         => $new_order_customer_contact_id,
            'customer_sources'             => $customer_sources,
            'discount'                     => $order->discount,
            'discount_description'         => $order->discount_description,
            'items_discount'               => $item_discount,
            'contact_info'                 => array(
                'email' => $order->contact->get('email'),
                'phone' => $order->contact->get('phone')
            ),
        ));
    }

    private function getOrderData($order)
    {
        $order_id = $order['id'];
        if (!$order_id) {
            throw new waException("Order not found", 404);
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

        foreach ($order_data['items'] as &$item) {
            $this->workupItems($item, $sku_stocks);
            if (isset($weights['skus'][$item['item']['sku_id']])) {
                $w = $weights['skus'][$item['item']['sku_id']];
            } else {
                $w = isset($weights[$item['id']]) ? $weights[$item['id']] : 0;
            }
            $item['weight'] = $w;
        }
        unset($item);

        return $order_data;
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
        return shopHelper::getShippingMethods($shipping_address, $order_items, $params);
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
                            $counts_htmls[$stock_id] = _w('%d left', '%d left', $stock['count']);
                        }
                    }
                    $sku['icon'] = shopHelper::getStockCountIcon($sku['count'], null, true);
                    $sku['icons'] = $icons;
                    $sku['count_htmls'] = $counts_htmls;
                }
            }
        }
        unset($sku);
    }

    public function getOrderStorefront($order)
    {
        $storefront = rtrim((string)ifset($order['params']['storefront'], ''), '/*');
        if (strpos($storefront, '/') !== false) {
            $storefront .= '/';
        }
        return $storefront;
    }

    /**
     * Convert tree-like structure where services are part of products
     * into flat list where services and products are on the same level.
     */
    protected function itemsForDiscount($order_currency, $items_tree)
    {
        $items = array();

        foreach ($items_tree as $index => $i) {
            $product = $i;
            $items[] = $this->workupProductItem($i['item'], $product, $order_currency, $index);

            if (!empty($i['services'])) {
                foreach ($i['services'] as $service) {
                    if (!empty($service['item'])) {
                        $items[] = $this->workupServiceItem($service, $product, $index);
                    }
                }
            }
        }

        return $items;
    }

    private function workupProductItem($i, &$product, $order_currency, $index)
    {
        unset($product['item']);
        if (!empty($product['services'])) {
            foreach ($product['services'] as &$s) {
                unset($s['item']);
            }
            unset($s);
        }

        $product_id = $i['product_id'];
        $sku_id = $i['sku_id'];
        unset($i['services']);

        $i += array(
            'type'               => 'product',
            'service_id'         => null,
            'service_variant_id' => null,
            'purchase_price'     => 0,
            'sku_code'           => '',
            'name'               => 'product_id='.$product_id,
        );

        if (!empty($product['skus'][$sku_id])) {
            $sku = $product['skus'][$sku_id];

            if (!empty($sku['name'])) {
                $i['name'] = sprintf('%s (%s)', ifempty($product['name'], $i['name']), $sku['name']);
            } else {
                $i['name'] = ifempty($product['name'], $i['name']);
            }

            if (empty($sku['fake'])) {
                $i['purchase_price'] = shop_currency($sku['purchase_price'], $product['currency'], $order_currency, false);
                $i['sku_code'] = $sku['sku'];
            } else {
                $i['purchase_price'] = 0;
                $i['sku_code'] = null;
            }

            $i['product'] = $product;
            $i['_index'] = $index;
        }
        return $i;
    }

    private function workupServiceItem($service, $product, $index)
    {
        $s = $service['item'];
        unset($service['item']);

        $s = array(
                'type'               => 'service',
                'price'              => $s['price'],
                'service_id'         => $service['id'],
                'service_variant_id' => 0,
                'purchase_price'     => 0,
                'name'               => 'service_id='.$s['service_id'],
            ) + $s;


        $variant = reset($service['variants']);
        $s['name'] = $service['name'].($variant['name'] ? ' ('.$variant['name'].')' : '');
        $s['service_variant_id'] = $variant['id'];
        $s['service'] = $service;
        $s['product'] = $product;
        $s['_parent_index'] = $index;

        return $s;
    }
}
