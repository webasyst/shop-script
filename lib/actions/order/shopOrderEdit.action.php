<?php

class shopOrderEditAction extends waViewAction
{
    private $crop_size = null;
    /**
     * @var shopOrderModel
     */
    private $order_model;

    public function __construct($params = null) {
        $this->order_model = new shopOrderModel();
        return parent::__construct($params);
    }

    public function execute()
    {
        $order_id = waRequest::get('id', null, waRequest::TYPE_INT);

        $form = null;
        $order = array();
        $shipping_address = array();

        // Existing order?
        if ($order_id) {
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
        }
        // New order?
        else {
            $currency = $this->getConfig()->getCurrency();
            $has_contacts_rights = shopHelper::getContactRights();
            $form = shopHelper::getCustomerForm();
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

        $this->view->assign(array(
            'form'     => $form,
            'order_storefront' => $this->getOrderStorefront($order),
            'order'    => $order,
            'stocks'   => $stocks,
            'currency' => $currency,
            'count_new' => $count_new,
            'taxes_count' => $taxes_count,
            'shipping_address' => $shipping_address,
            'has_contacts_rights' => $has_contacts_rights,
            'customer_validation_disabled' => wa()->getSetting('disable_backend_customer_form_validation'),
            'shipping_methods' => $this->getShipMethods($shipping_address, $order),
            'ignore_stock_count' => wa()->getSetting('ignore_stock_count'),
            'storefronts' => $this->getStorefronts(),
        ));
    }

    private function getShipMethods($shipping_address, $order)
    {
        if ($order) {
            $order_items = $order['items'];
            $order_currency = $order['currency'];
            $order_total = $order['subtotal'] - $order['discount'];
            if (!empty($order['params']['shipping_id'])) {
                $allow_external_for = array($order['params']['shipping_id']);
            } else {
                $allow_external_for = array();
            }
        } else {
            $allow_external_for = array();
            $order_items = array();
            $order_currency = null;
            $order_total = 0;
        }
        return shopHelper::getShippingMethods($shipping_address, $order_items, array(
            'currency' => $order_currency,
            'total_price' => $order_total,
            'no_external' => true,
            'allow_external_for' => $allow_external_for,
        ));
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
            $this->crop_size = $this->getConfig()->getImageSize('crop_small');
        }
        return $this->crop_size;
    }

    private function workupItems(&$item, $sku_stocks)
    {
        $size = $this->getCropSize();
        if (empty($item['image_id'])) {
            $item['url_crop_small'] = null;
        } else {
            $item['url_crop_small'] = shopImage::getUrl(
                array(
                    'id' => $item['image_id'],
                    'filename' => $item['image_filename'],
                    'product_id' => $item['id'],
                    'ext' => $item['ext']
                ),
                $size
            );
        }

        // aggregated stocks count icon for product
        if (empty($item['fake'])) {
            $item['icon'] = shopHelper::getStockCountIcon($item['count'], null, true);
        }

        foreach ($item['skus'] as &$sku) {
            if (empty($sku['fake'])) {
                // detaled stocks count icon for sku
                if (empty($sku_stocks[$sku['id']])) {
                    $sku['icon'] = shopHelper::getStockCountIcon($sku['count'], null, true);
                } else {
                    $icons = array();
                    foreach ($sku_stocks[$sku['id']] as $stock_id => $stock) {
                        $icon  = &$icons[$stock_id];
                        $icon  = shopHelper::getStockCountIcon($stock['count'], $stock_id)." ";
                        $icon .= $stock['count']." ";
                        $icon .= "<span class='small'>@".htmlspecialchars($stock['name'])."</span>";
                        unset($icon);
                    }
                    //$sku['icon'] = implode(', ', $icons);
                    $sku['icon'] = shopHelper::getStockCountIcon($sku['count'], null, true);
                    $sku['icons'] = $icons;
                }
            }
        }
        unset($sku);
    }

    public function getStorefronts()
    {
        $storefronts = array();
        foreach (wa()->getRouting()->getByApp('shop') as $domain => $domain_routes) {
            foreach ($domain_routes as $route) {
                $url = rtrim($domain.'/'.$route['url'], '/*');
                if (strpos($url, '/') !== false) {
                    $url .= '/';
                }
                $storefronts[] = $url;
            }
        }
        return $storefronts;
    }

    public function getOrderStorefront($order)
    {
        $storefront = rtrim((string) ifset($order['params']['storefront'], ''), '/*');
        if (strpos($storefront, '/') !== false) {
            $storefront .= '/';
        }
        return $storefront;
    }
}

