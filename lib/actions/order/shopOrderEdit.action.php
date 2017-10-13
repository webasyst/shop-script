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
        $client_contact_id = waRequest::get('customer_id', null, waRequest::TYPE_INT);

        $form = null;
        $order = array();
        $shipping_address = array();


        if ($order_id) { #Existing order
            $client_contact_id = null;
            $order = $this->getOrder($order_id);
            $currency = $order['currency'];
            if ($order['contact_id']) {
                $has_contacts_rights = shopHelper::getContactRights($order['contact_id']);

                $shipping_address = shopHelper::getOrderAddress($order['params'], 'shipping');

                if (!empty($order['contact_id'])) {
                    try {
                        $c = new waContact($order['contact_id']);
                        if ($shipping_address) {
                            $c['address.shipping'] = $shipping_address;
                        }
                        $form = shopHelper::getCustomerForm($c);
                    } catch (waException $e) {
                        // Contact does not exist; ignore. When $form is null, customer data saved in order is shown.
                    }
                }
            } else {
                $has_contacts_rights = shopHelper::getContactRights();
            }
        } else { #NEW ORDER
            $config = $this->getConfig();
            /**
             * @var shopConfig $config
             */
            $currency = $config->getCurrency();
            if ($client_contact_id) {
                try {
                    $c = new waContact($client_contact_id);
                    if ($shipping_address) {
                        $c['address.shipping'] = $shipping_address;
                    }
                    $form = shopHelper::getCustomerForm($c);
                    $has_contacts_rights = shopHelper::getContactRights($client_contact_id);
                } catch (waException $e) {
                    // Contact does not exist
                    $client_contact_id = null;
                }
            }
            if (!$client_contact_id) {
                $has_contacts_rights = shopHelper::getContactRights();
                $form = shopHelper::getCustomerForm();
            }
        }

        $stock_model = new shopStockModel();
        $stocks = $stock_model->getAll('id');

        $tax_model = new shopTaxModel();
        $taxes_count = $tax_model->countAll();

        $count_new = $this->order_model->getStateCounters('new');

        /**
         * Backend order edit page
         * @event backend_order_edit
         * @param array $order
         * @return array[string][string] $return[%plugin_id%] html output
         */
        $this->view->assign('backend_order_edit', wa()->event('backend_order_edit', $order));

        $shipping_methods = $this->getShipMethods($shipping_address, $order);

        $discount = array(
            'description'    => '',
            'items_discount' => array(),
            'value'          => 0,
        );

        if (!empty($order['id'])) {
            if (empty($c)) {
                $c = new waContact();
            }
            $data = array(
                'id'       => $order['id'],
                'currency' => $order['currency'],
                'items'    => $this->itemsForDiscount($order['currency'], $order['items']),
                'contact'  => $c,
                'total'    => $order['subtotal'],
            );
            unset($data['shipping']);

            $discount['value'] = shopDiscounts::calculate($data, false, $discount['description']);

            if (isset($data['shipping']) && ($data['shipping'] == 0)) {
                foreach ($shipping_methods as &$m) {
                    if (!is_string($m['rate'])) {
                        $m['rate'] = 0;
                    }
                    unset($m);
                }
            }

            $template = array(
                'product' => _w('Total discount for this order item: %s.'),
                'service' => _w('Total discount for this service: %s.'),
            );
            foreach ($data['items'] as $id => $item) {
                $item['total_discount'] = round(ifset($item['total_discount'], 0), 4);
                if (!empty($item['total_discount'])) {
                    switch ($item['type']) {
                        case 'service':
                            $selector = sprintf('%d_%d', $item['_parent_index'], $item['service_id']);
                            break;
                        default:
                            $selector = $item['_index'];
                            break;
                    }
                    $discount['items_discount'][] = array(
                        'value'    => $item['total_discount'],
                        'html'     => sprintf($template[$item['type']], shop_currency_html(-$item['total_discount'], $data['currency'], $data['currency'])),
                        'selector' => $selector,
                    );
                }
            }
        }


        $this->view->assign(array(
            'form'                         => $form,
            'order_storefront'             => $this->getOrderStorefront($order),
            'order'                        => $order,
            'stocks'                       => $stocks,
            'currency'                     => $currency,
            'count_new'                    => $count_new,
            'taxes_count'                  => $taxes_count,
            'shipping_address'             => $shipping_address,
            'has_contacts_rights'          => $has_contacts_rights,
            'customer_validation_disabled' => wa()->getSetting('disable_backend_customer_form_validation'),
            'shipping_methods'             => $shipping_methods,
            'ignore_stock_count'           => wa()->getSetting('ignore_stock_count'),
            'storefronts'                  => shopHelper::getStorefronts(),
            'new_order_for_client'         => $client_contact_id,
            'discount'                     => $discount,
        ));
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

    private function getOrder($order_id)
    {
        $order = $this->order_model->getOrder($order_id, true, true);
        if (!$order) {
            throw new waException("Order not found", 404);
        }
        $order['shipping_id'] = ifset($order['params']['shipping_id'], '').'.'.ifset($order['params']['shipping_rate_id'], '');

        $sku_ids = array();
        foreach ($order['items'] as $item) {
            foreach ($item['skus'] as $sku) {
                if (empty($sku['fake'])) {
                    $sku_ids[] = $sku['id'];
                }
            }
        }
        $sku_stocks = $this->getSkuStocks(array_unique($sku_ids));

        $subtotal = 0;
        $product_ids = array();

        foreach ($order['items'] as $i) {
            $product_ids[] = $i['id'];
            $subtotal += $i['item']['price'] * $i['item']['quantity'];
        }
        $order['subtotal'] = $subtotal;
        $product_ids = array_unique($product_ids);
        $feature_model = new shopFeatureModel();
        $f = $feature_model->getByCode('weight');
        if (!$f) {
            $values = array();
        } else {
            $values_model = $feature_model->getValuesModel($f['type']);
            $values = $values_model->getProductValues($product_ids, $f['id']);
        }

        foreach ($order['items'] as &$item) {
            if (isset($values['skus'][$item['item']['sku_id']])) {
                $w = $values['skus'][$item['item']['sku_id']];
            } else {
                $w = isset($values[$item['id']]) ? $values[$item['id']] : 0;
            }
            $this->workupItems($item, $sku_stocks);
            $item['quantity'] = $item['item']['quantity'];
            $item['weight'] = $w;
        }
        unset($item);

        return $order;
    }

    private function getSkuStocks($sku_ids)
    {
        if (!$sku_ids) {
            return array();
        }
        $product_stocks_model = new shopProductStocksModel();
        return $product_stocks_model->getBySkuId($sku_ids);
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

            $i['purchase_price'] = shop_currency($sku['purchase_price'], $product['currency'], $order_currency, false);
            $i['sku_code'] = $sku['sku'];
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
